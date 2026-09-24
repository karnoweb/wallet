<?php

namespace Karnoweb\Wallet\Services;

use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Computes the three balance figures the package exposes. All queries
 * use SQL aggregates; none of them instantiate every transaction/credit
 * row into PHP.
 */
class BalanceService
{
    public function __construct(protected CreditService $credits)
    {
    }

    /**
     * Total effective balance: SUM(amount * sign) over this wallet's
     * transactions. A wallet transaction created successfully by a
     * package operation is considered effective/final for V1.
     */
    public function balance(Wallet $wallet): int
    {
        $transactionClass = ConfiguredModels::transaction();

        // MySQL stores amount as UNSIGNED; `amount * sign` overflows when
        // sign is -1. Cast to signed so debit rows contribute negatively
        // on MySQL/MariaDB while SQLite/Postgres keep the simple form.
        $aggregate = $this->balanceAggregateExpression();

        return (int) $transactionClass::query()
            ->where('wallet_id', $wallet->id)
            ->selectRaw("{$aggregate} as aggregate_balance")
            ->value('aggregate_balance');
    }

    protected function balanceAggregateExpression(): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => 'COALESCE(SUM(CAST(amount AS SIGNED) * sign), 0)',
            default => 'COALESCE(SUM(amount * sign), 0)',
        };
    }

    /**
     * How much of this wallet can be spent for the given context
     * (club/service scopes), i.e. the sum of remaining_amount across
     * currently eligible credits.
     */
    public function spendableBalance(Wallet $wallet, array|WalletContext $context = []): int
    {
        $context = WalletContext::fromArray($context);
        $at = $context->occurredAt ?? now();
        $serviceIds = $context->scopes['service'] ?? [];

        $candidates = $this->credits->eligibleCandidates($wallet, $context->clubId, $serviceIds, false, $at);

        return (int) $candidates->sum('remaining_amount');
    }

    /**
     * Sum of remaining credit with cash_withdrawable=true and valid
     * dates. Club/service restrictions do not apply to whether money can
     * be reported as withdrawable in aggregate; they are enforced at
     * Deduct time against the requested context.
     */
    public function withdrawableBalance(Wallet $wallet, array|WalletContext $context = []): int
    {
        $context = WalletContext::fromArray($context);
        $at = $context->occurredAt ?? now();

        $creditClass = ConfiguredModels::credit();

        return (int) $creditClass::query()
            ->where('wallet_id', $wallet->id)
            ->where('cash_withdrawable', true)
            ->where('remaining_amount', '>', 0)
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            })
            ->sum('remaining_amount');
    }
}
