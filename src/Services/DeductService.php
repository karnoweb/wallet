<?php

namespace Karnoweb\Wallet\Services;

use Karnoweb\Wallet\Support\AtomicWalletTransaction;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\SpendSegment;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletDeducted;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Deduct (cash withdrawal) flow (blueprint section 19).
 * Identical to Payment except only credits with `cash_withdrawable=true`
 * may be selected, and a negative-balance policy never makes a
 * restricted, non-cashable credit cashable.
 */
class DeductService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
    ) {
    }

    public function deduct(Wallet $wallet, int $amount, WalletContext $context, array $settings): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deduct amount must be greater than zero.');
        }

        $segments = SpendSegment::buildFor($amount, $context->segments, $context->scopes);

        $payload = $this->buildPayload($wallet, $amount, $context, $segments);

        $result = AtomicWalletTransaction::run(function () use ($wallet, $amount, $context, $settings, $segments, $payload) {
            $resolution = $this->idempotency->resolveOperation(
                WalletOperationType::Deduct,
                $payload,
                $context->idempotencyKey,
                (bool) ($settings['idempotency_required'] ?? false)
            );

            if (! $resolution['is_new']) {
                return [
                    'transaction' => $this->existingTransaction($resolution['operation']->id),
                    'allocations' => null,
                    'replay' => true,
                ];
            }

            $operation = $resolution['operation'];

            $transaction = ConfiguredModels::newTransaction();
            $transaction->fill([
                'wallet_id' => $wallet->id,
                'causer_id' => $context->causerId,
                'transaction_id' => $context->transactionId,
                'amount' => $amount,
                'sign' => WalletSign::Debit->value,
                'type' => WalletTransactionType::Deduct->value,
                'description' => $context->description,
                'operation_id' => $operation->id,
                'club_id' => $context->clubId,
            ]);

            if ($context->transactionable) {
                $transaction->transactionable()->associate($context->transactionable);
            }

            $transaction->save();

            $allowNegative = (bool) ($settings['allow_negative'] ?? false);
            $strategy = app($settings['credit_selection_strategy'] ?? FifoCreditSelectionStrategy::class);

            $allAllocations = collect();

            foreach ($segments as $segment) {
                $segmentResult = $this->allocations->allocateSegment(
                    $wallet,
                    $transaction,
                    $segment,
                    $context->clubId,
                    true, // requireCashWithdrawable
                    $strategy,
                    $context->occurredAt,
                    $allowNegative
                );

                $allAllocations = $allAllocations->merge($segmentResult['allocations']);
            }

            return [
                'transaction' => $transaction,
                'allocations' => $allAllocations,
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            event(new WalletDeducted($result['transaction'], $result['allocations']));
        }

        return $result['transaction'];
    }

    /**
     * @param  array<int, SpendSegment>  $segments
     */
    protected function buildPayload(Wallet $wallet, int $amount, WalletContext $context, array $segments): array
    {
        return [
            'operation' => 'deduct',
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'club_id' => $context->clubId,
            'transactionable_type' => $context->transactionable ? get_class($context->transactionable) : null,
            'transactionable_id' => $context->transactionable?->getKey(),
            'transaction_id' => $context->transactionId,
            'segments' => array_map(fn (SpendSegment $segment) => $segment->toArray(), $segments),
        ];
    }

    protected function existingTransaction(int $operationId): WalletTransaction
    {
        $transactionClass = ConfiguredModels::transaction();

        return $transactionClass::query()->where('operation_id', $operationId)->firstOrFail();
    }
}
