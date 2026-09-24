<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\DTOs\WalletOperationResult;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletTransferred;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Transfer flow (blueprint section 20): one atomic
 * WalletOperation moving value from a source wallet to a destination
 * wallet while preserving credit lineage. Every source credit consumed
 * produces exactly one destination credit (`parent_credit_id` +
 * `source_wallet_id`); credits from different source clubs are never
 * flattened into one destination credit.
 */
class TransferService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
        protected CreditService $credits,
    ) {
    }

    public function transfer(Wallet $source, Wallet $destination, int $amount, WalletContext $context, array $settings): WalletOperationResult
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        if ($source->id === $destination->id) {
            throw new WalletException('Cannot transfer a wallet to itself.');
        }

        $payload = [
            'operation' => 'transfer',
            'source_wallet_id' => $source->id,
            'destination_wallet_id' => $destination->id,
            'amount' => $amount,
            'club_id' => $context->clubId,
            'transaction_id' => $context->transactionId,
        ];

        $resolution = $this->idempotency->resolveOperation(
            WalletOperationType::Transfer,
            $payload,
            $context->idempotencyKey,
            (bool) ($settings['idempotency_required'] ?? false)
        );

        if (! $resolution['is_new']) {
            return $this->existingResult($resolution['operation']);
        }

        $operation = $resolution['operation'];

        $result = DB::transaction(function () use ($source, $destination, $amount, $context, $settings, $operation) {
            $sourceTransaction = ConfiguredModels::newTransaction();
            $sourceTransaction->fill([
                'wallet_id' => $source->id,
                'causer_id' => $context->causerId,
                'transaction_id' => $context->transactionId,
                'amount' => $amount,
                'sign' => WalletSign::Debit->value,
                'type' => WalletTransactionType::Transfer->value,
                'description' => $context->description,
                'operation_id' => $operation->id,
                'club_id' => $context->clubId,
            ]);
            $sourceTransaction->save();

            $destinationTransaction = ConfiguredModels::newTransaction();
            $destinationTransaction->fill([
                'wallet_id' => $destination->id,
                'causer_id' => $context->causerId,
                'transaction_id' => $context->transactionId,
                'amount' => $amount,
                'sign' => WalletSign::Credit->value,
                'type' => WalletTransactionType::Transfer->value,
                'description' => $context->description,
                'operation_id' => $operation->id,
                'club_id' => $context->clubId,
            ]);
            $destinationTransaction->save();

            $allowNegative = (bool) ($settings['allow_negative'] ?? false);
            $strategy = app($settings['credit_selection_strategy'] ?? FifoCreditSelectionStrategy::class);

            $segmentResult = $this->allocations->allocateUnscoped(
                $source,
                $sourceTransaction,
                $amount,
                false,
                $strategy,
                $context->occurredAt,
                $allowNegative
            );

            $destinationCredits = collect();

            foreach ($segmentResult['plan'] as $entry) {
                $sourceCredit = $entry['credit'];
                $allocatedAmount = $entry['amount'];

                $destinationCredit = $this->credits->createCredit([
                    'wallet_id' => $destination->id,
                    'source_transaction_id' => $destinationTransaction->id,
                    'parent_credit_id' => $sourceCredit->id,
                    'source_wallet_id' => $source->id,
                    'original_amount' => $allocatedAmount,
                    'remaining_amount' => $allocatedAmount,
                    'starts_at' => $sourceCredit->starts_at,
                    'expires_at' => $sourceCredit->expires_at,
                    'expire_action' => $sourceCredit->expire_action->value,
                    'cash_withdrawable' => $sourceCredit->cash_withdrawable,
                ]);

                // Preserve existing credit restrictions by default.
                $this->credits->copyScopes($sourceCredit, $destinationCredit);

                $destinationCredits->push($destinationCredit);
            }

            return [
                'sourceTransaction' => $sourceTransaction,
                'destinationTransaction' => $destinationTransaction,
                'sourceAllocations' => $segmentResult['allocations'],
                'destinationCredits' => $destinationCredits,
            ];
        });

        event(new WalletTransferred(
            $result['sourceTransaction'],
            $result['destinationTransaction'],
            $result['sourceAllocations'],
            $result['destinationCredits']
        ));

        return new WalletOperationResult($operation, $result['sourceTransaction'], $result['destinationTransaction']);
    }

    protected function existingResult(\Karnoweb\Wallet\Models\WalletOperation $operation): WalletOperationResult
    {
        $transactionClass = ConfiguredModels::transaction();

        $source = $transactionClass::query()
            ->where('operation_id', $operation->id)
            ->where('sign', WalletSign::Debit->value)
            ->firstOrFail();

        $destination = $transactionClass::query()
            ->where('operation_id', $operation->id)
            ->where('sign', WalletSign::Credit->value)
            ->firstOrFail();

        return new WalletOperationResult($operation, $source, $destination);
    }
}
