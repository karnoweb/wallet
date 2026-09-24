<?php

namespace Karnoweb\Wallet\Services;

use Karnoweb\Wallet\Support\AtomicWalletTransaction;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\DTOs\WalletOperationResult;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletCreditGranted;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Grant flow (blueprint section 21): a transfer plus a
 * rule snapshot. Source allocation works exactly like Transfer, but the
 * destination credit's restrictions come from the supplied
 * {@see CreditRules} instead of being copied from the source credit.
 * Rules are frozen at grant time: changing the source
 * Organization/Contract configuration afterwards never changes credits
 * that were already granted.
 *
 * The underlying WalletTransaction rows use `type=transfer` (a Grant IS
 * a transfer, at the ledger level); the shared WalletOperation uses
 * `type=grant` so idempotency/reporting can distinguish the two.
 */
class GrantService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
        protected CreditService $credits,
    ) {
    }

    public function grant(Wallet $source, Wallet $destination, int $amount, CreditRules $rules, WalletContext $context, array $settings): WalletOperationResult
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Grant amount must be greater than zero.');
        }

        if ($source->id === $destination->id) {
            throw new WalletException('Cannot grant a wallet to itself.');
        }

        $payload = [
            'operation' => 'grant',
            'source_wallet_id' => $source->id,
            'destination_wallet_id' => $destination->id,
            'amount' => $amount,
            'club_id' => $context->clubId,
            'rules' => $rules->toArray(),
        ];

        $result = AtomicWalletTransaction::run(function () use ($source, $destination, $amount, $rules, $context, $settings, $payload) {
            $this->lockWalletsDeterministically($source, $destination);

            $resolution = $this->idempotency->resolveOperation(
                WalletOperationType::Grant,
                $payload,
                $context->idempotencyKey,
                (bool) ($settings['idempotency_required'] ?? false)
            );

            if (! $resolution['is_new']) {
                return [
                    'result' => $this->existingResult($resolution['operation']),
                    'destinationCredits' => null,
                    'replay' => true,
                ];
            }

            $operation = $resolution['operation'];

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

            $cashWithdrawable = $rules->cashWithdrawable ?? (bool) ($settings['cash_withdrawable_default'] ?? true);

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
                    'starts_at' => $rules->startsAt,
                    'expires_at' => $rules->expiresAt,
                    'expire_action' => $rules->expireAction->value,
                    'cash_withdrawable' => $cashWithdrawable,
                ]);

                // Rules are a snapshot of the supplied CreditRules, not a
                // copy of the source credit's scopes.
                $this->credits->createScopesFromRules($destinationCredit, $rules);

                $destinationCredits->push($destinationCredit);
            }

            return [
                'result' => new WalletOperationResult($operation, $sourceTransaction, $destinationTransaction),
                'destinationCredits' => $destinationCredits,
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            event(new WalletCreditGranted(
                $result['result']->sourceTransaction,
                $result['result']->destinationTransaction,
                $result['destinationCredits']
            ));
        }

        return $result['result'];
    }

    /**
     * Lock both wallet rows in ascending primary-key order so concurrent
     * A→B and B→A grants cannot deadlock on wallet locks.
     */
    protected function lockWalletsDeterministically(Wallet $source, Wallet $destination): void
    {
        $walletClass = ConfiguredModels::wallet();

        $ids = collect([$source->id, $destination->id])->unique()->sort()->values();

        foreach ($ids as $id) {
            $walletClass::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }
    }

    protected function existingResult(WalletOperation $operation): WalletOperationResult
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
