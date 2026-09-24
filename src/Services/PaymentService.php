<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\SpendSegment;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletPaid;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Payment flow (blueprint section 18): spends one or more
 * segments of a payment amount from eligible credits using the
 * configured selection strategy (FIFO by default), inside one DB
 * transaction, dispatching {@see WalletPaid} only after a successful
 * commit.
 */
class PaymentService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
    ) {
    }

    public function pay(Wallet $wallet, int $amount, WalletContext $context, array $settings): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $segments = SpendSegment::buildFor($amount, $context->segments, $context->scopes);

        $payload = $this->buildPayload($wallet, $amount, $context, $segments);

        $resolution = $this->idempotency->resolveOperation(
            WalletOperationType::Payment,
            $payload,
            $context->idempotencyKey,
            (bool) ($settings['idempotency_required'] ?? false)
        );

        if (! $resolution['is_new']) {
            return $this->existingTransaction($resolution['operation']->id);
        }

        $operation = $resolution['operation'];

        $result = DB::transaction(function () use ($wallet, $amount, $context, $settings, $segments, $operation) {
            $transaction = ConfiguredModels::newTransaction();
            $transaction->fill([
                'wallet_id' => $wallet->id,
                'causer_id' => $context->causerId,
                'transaction_id' => $context->transactionId,
                'amount' => $amount,
                'sign' => WalletSign::Debit->value,
                'type' => WalletTransactionType::Payment->value,
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
                    false,
                    $strategy,
                    $context->occurredAt,
                    $allowNegative
                );

                $allAllocations = $allAllocations->merge($segmentResult['allocations']);
            }

            return ['transaction' => $transaction, 'allocations' => $allAllocations];
        });

        event(new WalletPaid($result['transaction'], $result['allocations']));

        return $result['transaction'];
    }

    /**
     * @param  array<int, SpendSegment>  $segments
     */
    protected function buildPayload(Wallet $wallet, int $amount, WalletContext $context, array $segments): array
    {
        return [
            'operation' => 'payment',
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
