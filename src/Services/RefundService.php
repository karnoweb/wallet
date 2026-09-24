<?php

namespace Karnoweb\Wallet\Services;

use Karnoweb\Wallet\Support\AtomicWalletTransaction;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletRefunded;
use Karnoweb\Wallet\Exceptions\InvalidRefund;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Refund flow (blueprint section 22). Refund never
 * creates unrestricted money: it only restores previously consumed
 * allocations, preserving every restriction the original credit had
 * (club/service scopes, cash_withdrawable). The default partial-refund
 * rule restores original consume allocations in original allocation id
 * order.
 *
 * Refundable-amount calculation and restore execution run inside one
 * database transaction with row locks on the payment, its consume
 * allocations, and the related credits so concurrent full/partial
 * refunds cannot over-restore.
 */
class RefundService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
        protected CreditService $credits,
    ) {
    }

    public function refund(Wallet $wallet, WalletTransaction $payment, ?int $amount, WalletContext $context, array $settings): WalletTransaction
    {
        if ((int) $payment->wallet_id !== (int) $wallet->id) {
            throw new InvalidRefund('The payment transaction does not belong to this wallet.');
        }

        if ($payment->type !== WalletTransactionType::Payment) {
            throw new InvalidRefund('Only payment transactions can be refunded.');
        }

        $segmentKeys = array_values((array) $context->option('segment_keys', []));

        // Payload uses the caller-requested amount (or null for "full
        // remaining"). The concrete amount is resolved inside the TX after
        // locks so concurrent refunds see a consistent refundable total.
        // For idempotency hashing, null is normalized to the sentinel
        // "full" so retries of a full refund match even when the live
        // remaining changes after the first success.
        $payloadAmount = $amount;

        $result = AtomicWalletTransaction::run(function () use ($wallet, $payment, $amount, $context, $settings, $segmentKeys, $payloadAmount) {
            $transactionClass = ConfiguredModels::transaction();
            $allocationClass = ConfiguredModels::allocation();

            /** @var WalletTransaction $lockedPayment */
            $lockedPayment = $transactionClass::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $consumeQuery = $allocationClass::query()
                ->where('wallet_transaction_id', $lockedPayment->id)
                ->where('type', WalletAllocationType::Consume->value)
                ->orderBy('id')
                ->lockForUpdate();

            if (! empty($segmentKeys)) {
                $consumeQuery->whereIn('segment_key', $segmentKeys);
            }

            $consumeAllocations = $consumeQuery->get();

            if ($consumeAllocations->isEmpty()) {
                throw new InvalidRefund('No matching allocations were found for this refund request.');
            }

            if (! empty($segmentKeys)) {
                $foundKeys = $consumeAllocations->pluck('segment_key')->filter()->unique()->all();
                $missing = array_diff($segmentKeys, $foundKeys);

                if (! empty($missing)) {
                    throw new InvalidRefund('One or more segment keys do not belong to the original payment allocations.');
                }
            }

            // Lock related credits in id order before reading remaining /
            // restore totals so concurrent refunds serialize on the same
            // credit rows.
            $this->credits->lockCredits($consumeAllocations->pluck('wallet_credit_id')->unique()->all());

            $restoredTotals = $allocationClass::query()
                ->where('type', WalletAllocationType::Restore->value)
                ->whereIn('original_allocation_id', $consumeAllocations->pluck('id'))
                ->selectRaw('original_allocation_id, SUM(amount) as total_restored')
                ->groupBy('original_allocation_id')
                ->pluck('total_restored', 'original_allocation_id');

            $refundablePerAllocation = $consumeAllocations->mapWithKeys(function ($allocation) use ($restoredTotals) {
                $restored = (int) ($restoredTotals[$allocation->id] ?? 0);

                return [$allocation->id => $allocation->amount - $restored];
            });

            $totalRefundable = (int) $refundablePerAllocation->sum();

            $payload = [
                'operation' => 'refund',
                'wallet_id' => $wallet->id,
                'payment_transaction_id' => $lockedPayment->id,
                // Stable hash input: explicit amount, or "full" for null so a
                // successful full-refund retry still matches after remaining
                // drops to zero.
                'amount' => $payloadAmount === null ? 'full' : $payloadAmount,
                'segment_keys' => $segmentKeys,
            ];

            $resolution = $this->idempotency->resolveOperation(
                WalletOperationType::Refund,
                $payload,
                $context->idempotencyKey,
                (bool) ($settings['idempotency_required'] ?? false)
            );

            if (! $resolution['is_new']) {
                return [
                    'transaction' => $this->existingTransaction($resolution['operation']->id),
                    'payment' => $lockedPayment,
                    'restoreAllocations' => null,
                    'replay' => true,
                ];
            }

            $requested = $payloadAmount ?? $totalRefundable;

            if ($totalRefundable <= 0) {
                throw new InvalidRefund('No refundable amount remains for this payment.');
            }

            if ($requested <= 0) {
                throw new InvalidArgumentException('Refund amount must be greater than zero.');
            }

            if ($requested > $totalRefundable) {
                throw new InvalidRefund("Refund amount ({$requested}) exceeds the refundable amount ({$totalRefundable}).");
            }

            $plan = [];
            $remaining = $requested;

            foreach ($consumeAllocations as $allocation) {
                if ($remaining <= 0) {
                    break;
                }

                $refundable = $refundablePerAllocation[$allocation->id];

                if ($refundable <= 0) {
                    continue;
                }

                $take = min($refundable, $remaining);
                $plan[] = ['allocation' => $allocation, 'amount' => $take];
                $remaining -= $take;
            }

            $operation = $resolution['operation'];

            $refundTransaction = ConfiguredModels::newTransaction();
            $refundTransaction->fill([
                'wallet_id' => $wallet->id,
                'causer_id' => $context->causerId,
                'transaction_id' => $context->transactionId,
                'amount' => $requested,
                'sign' => WalletSign::Credit->value,
                'type' => WalletTransactionType::Refund->value,
                'description' => $context->description,
                'operation_id' => $operation->id,
                'club_id' => $context->clubId ?? $lockedPayment->club_id,
            ]);
            $refundTransaction->save();

            $restoreAllocations = collect();

            foreach ($plan as $entry) {
                $restoreAllocations->push(
                    $this->allocations->restore($refundTransaction, $entry['allocation'], $entry['amount'])
                );
            }

            return [
                'transaction' => $refundTransaction,
                'payment' => $lockedPayment,
                'restoreAllocations' => $restoreAllocations,
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            event(new WalletRefunded($result['transaction'], $result['payment'], $result['restoreAllocations']));
        }

        return $result['transaction'];
    }

    protected function existingTransaction(int $operationId): WalletTransaction
    {
        $transactionClass = ConfiguredModels::transaction();

        return $transactionClass::query()->where('operation_id', $operationId)->firstOrFail();
    }
}
