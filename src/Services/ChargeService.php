<?php

namespace Karnoweb\Wallet\Services;

use Karnoweb\Wallet\Support\AtomicWalletTransaction;
use InvalidArgumentException;
use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Events\WalletCharged;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Implements the Charge flow (blueprint section 17): creates a positive
 * WalletTransaction plus an unrestricted (unless advanced CreditRules are
 * supplied) WalletCredit funding it. Everything happens inside one DB
 * transaction; the {@see WalletCharged} event is only dispatched after a
 * successful commit.
 */
class ChargeService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected CreditService $credits,
    ) {
    }

    public function charge(Wallet $wallet, int $amount, WalletContext $context, array $settings, ?CreditRules $rules = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Charge amount must be greater than zero.');
        }

        $rules ??= CreditRules::fromArray([]);

        $payload = [
            'operation' => 'charge',
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'club_id' => $context->clubId,
            'transactionable_type' => $context->transactionable ? get_class($context->transactionable) : null,
            'transactionable_id' => $context->transactionable?->getKey(),
            'transaction_id' => $context->transactionId,
            'rules' => $rules->toArray(),
        ];

        $result = AtomicWalletTransaction::run(function () use ($wallet, $amount, $context, $settings, $rules, $payload) {
            $resolution = $this->idempotency->resolveOperation(
                WalletOperationType::Charge,
                $payload,
                $context->idempotencyKey,
                (bool) ($settings['idempotency_required'] ?? false)
            );

            if (! $resolution['is_new']) {
                return [
                    'transaction' => $this->existingTransaction($resolution['operation']->id),
                    'credit' => null,
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
                'sign' => WalletSign::Credit->value,
                'type' => WalletTransactionType::Charge->value,
                'description' => $context->description,
                'operation_id' => $operation->id,
                'club_id' => $context->clubId,
            ]);

            if ($context->transactionable) {
                $transaction->transactionable()->associate($context->transactionable);
            }

            $transaction->save();

            $cashWithdrawable = $rules->cashWithdrawable ?? (bool) ($settings['cash_withdrawable_default'] ?? true);

            $credit = $this->credits->createCredit([
                'wallet_id' => $wallet->id,
                'source_transaction_id' => $transaction->id,
                'original_amount' => $amount,
                'remaining_amount' => $amount,
                'starts_at' => $rules->startsAt,
                'expires_at' => $rules->expiresAt,
                'expire_action' => $rules->expireAction->value,
                'cash_withdrawable' => $cashWithdrawable,
            ]);

            $this->credits->createScopesFromRules($credit, $rules);

            return [
                'transaction' => $transaction,
                'credit' => $credit,
                'replay' => false,
            ];
        });

        if (! $result['replay']) {
            event(new WalletCharged($result['transaction'], $result['credit']));
        }

        return $result['transaction'];
    }

    protected function existingTransaction(int $operationId): WalletTransaction
    {
        $transactionClass = ConfiguredModels::transaction();

        return $transactionClass::query()->where('operation_id', $operationId)->firstOrFail();
    }
}
