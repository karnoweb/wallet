<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Facades\DB;
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
 */
class RefundService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
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

        $allocationClass = ConfiguredModels::allocation();

        $consumeQuery = $allocationClass::query()
            ->where('wallet_transaction_id', $payment->id)
            ->where('type', WalletAllocationType::Consume->value)
            ->orderBy('id');

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

        $requested = $amount ?? $totalRefundable;

        if ($requested <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        // Resolve idempotency BEFORE validating the requested amount
        // against the *currently remaining* refundable total. By the
        // time a retried request arrives, the first call has already
        // consumed the refundable balance (that is the whole point of a
        // refund), so re-checking against live state here would make a
        // legitimate retry with the same key/payload fail instead of
        // transparently returning the original result.
        $payload = [
            'operation' => 'refund',
            'wallet_id' => $wallet->id,
            'payment_transaction_id' => $payment->id,
            'amount' => $requested,
            'segment_keys' => $segmentKeys,
        ];

        $resolution = $this->idempotency->resolveOperation(
            WalletOperationType::Refund,
            $payload,
            $context->idempotencyKey,
            (bool) ($settings['idempotency_required'] ?? false)
        );

        if (! $resolution['is_new']) {
            return $this->existingTransaction($resolution['operation']->id);
        }

        $operation = $resolution['operation'];

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

        $result = DB::transaction(function () use ($wallet, $payment, $requested, $context, $plan, $operation) {
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
                'club_id' => $context->clubId ?? $payment->club_id,
            ]);
            $refundTransaction->save();

            $restoreAllocations = collect();

            foreach ($plan as $entry) {
                $restoreAllocations->push(
                    $this->allocations->restore($refundTransaction, $entry['allocation'], $entry['amount'])
                );
            }

            return ['transaction' => $refundTransaction, 'restoreAllocations' => $restoreAllocations];
        });

        event(new WalletRefunded($result['transaction'], $payment, $result['restoreAllocations']));

        return $result['transaction'];
    }

    protected function existingTransaction(int $operationId): WalletTransaction
    {
        $transactionClass = ConfiguredModels::transaction();

        return $transactionClass::query()->where('operation_id', $operationId)->firstOrFail();
    }
}
