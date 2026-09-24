<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Wallet\Enums\CreditExpireAction;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletCreditExpired;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Expiration flow (blueprint section 23). Processes
 * credits whose `expires_at <= now` and `remaining_amount > 0` in
 * chunks, locking each credit before acting on it. Every expiration is
 * idempotent via a deterministic key
 * (`expiration:<credit_id>:<expires_at timestamp>`), so running the
 * command twice never duplicates financial effects.
 */
class ExpirationService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected AllocationService $allocations,
    ) {
    }

    /**
     * Process every currently due credit in chunks, never letting one
     * credit's failure (e.g. an unresolvable "return" lineage) stop the
     * rest of the batch.
     *
     * @return array{processed: int, skipped: int, failed: array<int, array{credit_id: int, message: string}>}
     */
    public function expireDueCredits(int $chunkSize): array
    {
        $summary = ['processed' => 0, 'skipped' => 0, 'failed' => []];

        $creditClass = ConfiguredModels::credit();

        $creditClass::query()
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($credits) use (&$summary) {
                foreach ($credits as $credit) {
                    try {
                        $processed = $this->expireCredit($credit);
                        $processed ? $summary['processed']++ : $summary['skipped']++;
                    } catch (\Throwable $e) {
                        $summary['failed'][] = [
                            'credit_id' => $credit->id,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            });

        return $summary;
    }

    /**
     * Process the expiration of a single credit. Returns true when a
     * financial effect was produced, false when the credit was skipped
     * (no-op action, already processed, or nothing left to expire).
     */
    public function expireCredit(WalletCredit $credit): bool
    {
        if ($credit->expire_action === CreditExpireAction::None) {
            return false;
        }

        $idempotencyKey = sprintf('expiration:%d:%d', $credit->id, $credit->expires_at?->getTimestamp() ?? 0);

        $payload = [
            'operation' => 'expiration',
            'credit_id' => $credit->id,
            'expires_at' => $credit->expires_at?->toIso8601String(),
            'action' => $credit->expire_action->value,
        ];

        $resolution = $this->idempotency->resolveOperation(
            WalletOperationType::Expiration,
            $payload,
            $idempotencyKey,
            true
        );

        if (! $resolution['is_new']) {
            return false;
        }

        $operation = $resolution['operation'];

        $result = DB::transaction(function () use ($credit, $operation) {
            $creditClass = ConfiguredModels::credit();

            /** @var WalletCredit $locked */
            $locked = $creditClass::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            if ($locked->remaining_amount <= 0) {
                return null;
            }

            return match ($locked->expire_action) {
                CreditExpireAction::Burn => $this->burn($locked, $operation),
                CreditExpireAction::Return => $this->returnToSource($locked, $operation),
                default => null,
            };
        });

        if ($result === null) {
            return false;
        }

        event(new WalletCreditExpired($credit, $result['transaction'], $credit->expire_action->value));

        return true;
    }

    protected function burn(WalletCredit $credit, WalletOperation $operation): array
    {
        $amount = $credit->remaining_amount;

        $transaction = ConfiguredModels::newTransaction();
        $transaction->fill([
            'wallet_id' => $credit->wallet_id,
            'amount' => $amount,
            'sign' => WalletSign::Debit->value,
            'type' => WalletTransactionType::System->value,
            'description' => 'Credit expired (burn)',
            'operation_id' => $operation->id,
        ]);
        $transaction->save();

        $this->allocations->consume($transaction, collect([['credit' => $credit, 'amount' => $amount]]));

        return ['transaction' => $transaction];
    }

    /**
     * Returns the remaining value to the credit's source lineage: the
     * receiving wallet is debited, and the exact original allocation
     * that funded this credit's parent is restored, so lineage is never
     * lost. If the lineage cannot be resolved, this throws instead of
     * silently burning the value.
     */
    protected function returnToSource(WalletCredit $credit, WalletOperation $operation): array
    {
        $amount = $credit->remaining_amount;

        if (! $credit->parent_credit_id || ! $credit->source_wallet_id) {
            throw new WalletException(
                "Cannot return expiring credit #{$credit->id}: no parent credit / source wallet lineage is available."
            );
        }

        $transactionClass = ConfiguredModels::transaction();
        $allocationClass = ConfiguredModels::allocation();

        $sourceTransaction = $credit->sourceTransaction;

        $siblingDebit = $sourceTransaction
            ? $transactionClass::query()
                ->where('operation_id', $sourceTransaction->operation_id)
                ->where('wallet_id', $credit->source_wallet_id)
                ->where('sign', WalletSign::Debit->value)
                ->first()
            : null;

        $originalAllocation = $siblingDebit
            ? $allocationClass::query()
                ->where('wallet_transaction_id', $siblingDebit->id)
                ->where('wallet_credit_id', $credit->parent_credit_id)
                ->first()
            : null;

        if (! $originalAllocation) {
            throw new WalletException(
                "Cannot return expiring credit #{$credit->id}: the original source allocation could not be resolved."
            );
        }

        $debitTransaction = ConfiguredModels::newTransaction();
        $debitTransaction->fill([
            'wallet_id' => $credit->wallet_id,
            'amount' => $amount,
            'sign' => WalletSign::Debit->value,
            'type' => WalletTransactionType::System->value,
            'description' => 'Credit expired (return to source)',
            'operation_id' => $operation->id,
        ]);
        $debitTransaction->save();

        $this->allocations->consume($debitTransaction, collect([['credit' => $credit, 'amount' => $amount]]));

        $creditTransaction = ConfiguredModels::newTransaction();
        $creditTransaction->fill([
            'wallet_id' => $credit->source_wallet_id,
            'amount' => $amount,
            'sign' => WalletSign::Credit->value,
            'type' => WalletTransactionType::System->value,
            'description' => 'Credit expired (returned from destination wallet)',
            'operation_id' => $operation->id,
        ]);
        $creditTransaction->save();

        $this->allocations->restore($creditTransaction, $originalAllocation, $amount);

        return ['transaction' => $debitTransaction, 'returnTransaction' => $creditTransaction];
    }
}
