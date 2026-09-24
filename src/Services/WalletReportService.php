<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Karnoweb\Wallet\DTOs\WalletSummary;
use Karnoweb\Wallet\Enums\WalletSign;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Provides the Statement/Summary/Credit reporting APIs (blueprint
 * section 25). Every report paginates or aggregates in SQL; none of
 * them load an unbounded collection into PHP.
 */
class WalletReportService
{
    public function __construct(protected BalanceService $balance)
    {
    }

    public function statement(Wallet $wallet, array $filters = []): LengthAwarePaginator
    {
        $query = $this->applyFilters($wallet->transactions()->getQuery(), $filters);

        $query->orderByDesc('created_at')->orderByDesc('id');

        $requestedPerPage = (int) ($filters['per_page'] ?? config('wallet.reports.default_per_page', 30));
        $maxPerPage = (int) config('wallet.reports.max_per_page', 200);
        $perPage = $requestedPerPage > 0 ? min($requestedPerPage, $maxPerPage) : $maxPerPage;

        return $query->paginate($perPage);
    }

    public function summary(Wallet $wallet, array $filters = []): WalletSummary
    {
        $base = fn () => $this->applyFilters($wallet->transactions()->getQuery(), $filters);

        $totalsByType = (clone $base())
            ->selectRaw('type, SUM(amount) as total_amount')
            ->groupBy('type')
            ->pluck('total_amount', 'type');

        $transfersIn = (clone $base())
            ->where('type', WalletTransactionType::Transfer->value)
            ->where('sign', WalletSign::Credit->value)
            ->sum('amount');

        $transfersOut = (clone $base())
            ->where('type', WalletTransactionType::Transfer->value)
            ->where('sign', WalletSign::Debit->value)
            ->sum('amount');

        return new WalletSummary(
            balance: $this->balance->balance($wallet),
            spendableBalance: null,
            withdrawableBalance: $this->balance->withdrawableBalance($wallet),
            charges: (int) ($totalsByType[WalletTransactionType::Charge->value] ?? 0),
            payments: (int) ($totalsByType[WalletTransactionType::Payment->value] ?? 0),
            deducts: (int) ($totalsByType[WalletTransactionType::Deduct->value] ?? 0),
            refunds: (int) ($totalsByType[WalletTransactionType::Refund->value] ?? 0),
            transfersIn: (int) $transfersIn,
            transfersOut: (int) $transfersOut,
            from: isset($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from']) : null,
            to: isset($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to']) : null,
        );
    }

    /**
     * Advanced API: raw credit query builder for a wallet.
     */
    public function credits(Wallet $wallet, array $filters = []): Builder
    {
        $query = $wallet->credits()->getQuery();

        if (! empty($filters['active_only'])) {
            $query->where('remaining_amount', '>', 0);
        }

        return $query;
    }

    /**
     * Advanced API: every allocation belonging to a transaction.
     *
     * @return Collection<int, \Karnoweb\Wallet\Models\WalletAllocation>
     */
    public function allocations(WalletTransaction $transaction): Collection
    {
        return $transaction->allocations()->get();
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['types'])) {
            $query->whereIn('type', $filters['types']);
        }

        if (isset($filters['club_id'])) {
            $query->where('club_id', $filters['club_id']);
        }

        if (! empty($filters['club_ids'])) {
            $query->whereIn('club_id', $filters['club_ids']);
        }

        if (isset($filters['sign'])) {
            $query->where('sign', $filters['sign']);
        }

        if (! empty($filters['transactionable_type'])) {
            $query->where('transactionable_type', $filters['transactionable_type']);
        }

        if (isset($filters['transactionable_id'])) {
            $query->where('transactionable_id', $filters['transactionable_id']);
        }

        if (isset($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        return $query;
    }
}
