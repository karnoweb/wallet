<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Creates WalletCredit rows/scope snapshots and computes credit
 * eligibility (see the implementation blueprint, "Credit eligibility").
 *
 * A credit is eligible for a spend segment only when ALL of the
 * following hold:
 *
 * 1. remaining_amount > 0
 * 2. starts_at is null or <= operation time
 * 3. expires_at is null or > operation time
 * 4. if the credit has `club` scopes, the operation club is one of them
 * 5. if the credit has `service` scopes, the segment contains at least
 *    one allowed service scope
 * 6. for cash withdrawal, cash_withdrawable=true
 *
 * Absence of scopes of a given type means unrestricted for that type.
 * Rules are always read from the credit snapshot, never inferred from
 * host Organization/Contract models at spend time.
 */
class CreditService
{
    /**
     * Fetch already-filtered eligible candidate credits for a wallet,
     * pushing remaining/date/cash/scope filters into SQL so wallets with
     * thousands of credits do not load unrelated rows into PHP.
     */
    public function eligibleCandidates(
        Wallet $wallet,
        ?int $clubId,
        array $serviceIds,
        bool $requireCashWithdrawable,
        \DateTimeInterface $at
    ): Collection {
        $creditClass = ConfiguredModels::credit();

        $query = $creditClass::query()
            ->where('wallet_id', $wallet->id)
            ->where('remaining_amount', '>', 0)
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            });

        if ($requireCashWithdrawable) {
            $query->where('cash_withdrawable', true);
        }

        $this->applyClubScopeFilter($query, $clubId);
        $this->applyServiceScopeFilter($query, $serviceIds);

        return $query
            ->with('scopes')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Candidate credits for Transfer/Grant: club/service scopes are
     * intentionally ignored (see {@see \Karnoweb\Wallet\Services\AllocationService::allocateUnscoped()}).
     */
    public function unscopedEligibleCandidates(
        Wallet $wallet,
        bool $requireCashWithdrawable,
        \DateTimeInterface $at
    ): Collection {
        $creditClass = ConfiguredModels::credit();

        $query = $creditClass::query()
            ->where('wallet_id', $wallet->id)
            ->where('remaining_amount', '>', 0)
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            });

        if ($requireCashWithdrawable) {
            $query->where('cash_withdrawable', true);
        }

        return $query
            ->with('scopes')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Credits with club scopes require the operation club to match; credits
     * without club scopes are unrestricted. When the operation has no club,
     * only unrestricted (no club scope) credits are eligible.
     */
    protected function applyClubScopeFilter($query, ?int $clubId): void
    {
        if ($clubId === null) {
            $query->whereDoesntHave('scopes', function ($q) {
                $q->where('scope_type', 'club');
            });

            return;
        }

        $query->where(function ($q) use ($clubId) {
            $q->whereDoesntHave('scopes', function ($inner) {
                $inner->where('scope_type', 'club');
            })->orWhereHas('scopes', function ($inner) use ($clubId) {
                $inner->where('scope_type', 'club')->where('scope_id', $clubId);
            });
        });
    }

    /**
     * Credits with service scopes require at least one overlapping service
     * id on the segment; credits without service scopes are unrestricted.
     * When the segment provides no service ids, only unrestricted credits
     * are eligible.
     */
    protected function applyServiceScopeFilter($query, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));

        if ($serviceIds === []) {
            $query->whereDoesntHave('scopes', function ($q) {
                $q->where('scope_type', 'service');
            });

            return;
        }

        $query->where(function ($q) use ($serviceIds) {
            $q->whereDoesntHave('scopes', function ($inner) {
                $inner->where('scope_type', 'service');
            })->orWhereHas('scopes', function ($inner) use ($serviceIds) {
                $inner->where('scope_type', 'service')->whereIn('scope_id', $serviceIds);
            });
        });
    }

    public function unscopedIsEligible(WalletCredit $credit, bool $requireCashWithdrawable, \DateTimeInterface $at): bool
    {
        if ($credit->remaining_amount <= 0) {
            return false;
        }

        if ($credit->starts_at && $credit->starts_at->gt($at)) {
            return false;
        }

        if ($credit->expires_at && $credit->expires_at->lte($at)) {
            return false;
        }

        if ($requireCashWithdrawable && ! $credit->cash_withdrawable) {
            return false;
        }

        return true;
    }

    public function isEligible(
        WalletCredit $credit,
        ?int $clubId,
        array $serviceIds,
        bool $requireCashWithdrawable,
        \DateTimeInterface $at
    ): bool {
        if ($credit->remaining_amount <= 0) {
            return false;
        }

        if ($credit->starts_at && $credit->starts_at->gt($at)) {
            return false;
        }

        if ($credit->expires_at && $credit->expires_at->lte($at)) {
            return false;
        }

        if ($requireCashWithdrawable && ! $credit->cash_withdrawable) {
            return false;
        }

        return $this->matchesScopes($credit, $clubId, $serviceIds);
    }

    protected function matchesScopes(WalletCredit $credit, ?int $clubId, array $serviceIds): bool
    {
        $scopes = $credit->scopes;

        $clubScopes = $scopes->where('scope_type', 'club');

        if ($clubScopes->isNotEmpty()) {
            if ($clubId === null || ! $clubScopes->pluck('scope_id')->contains($clubId)) {
                return false;
            }
        }

        $serviceScopes = $scopes->where('scope_type', 'service');

        if ($serviceScopes->isNotEmpty()) {
            $allowed = $serviceScopes->pluck('scope_id')->all();

            if (empty(array_intersect($allowed, $serviceIds))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lock the given credit ids for update in ascending id order so
     * concurrent operations that touch overlapping credit sets cannot
     * deadlock, then return the locked rows keyed by id.
     *
     * @return Collection<int, WalletCredit> Keyed by credit id.
     */
    public function lockCredits(array $creditIds): Collection
    {
        $creditIds = array_values(array_unique(array_filter($creditIds)));

        if ($creditIds === []) {
            return collect();
        }

        sort($creditIds);

        $creditClass = ConfiguredModels::credit();

        return $creditClass::query()
            ->whereIn('id', $creditIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function createCredit(array $attributes): WalletCredit
    {
        $credit = ConfiguredModels::newCredit();
        $credit->fill($attributes);
        $credit->save();

        return $credit;
    }

    public function createScopesFromRules(WalletCredit $credit, CreditRules $rules): void
    {
        foreach ($rules->allowedClubIds as $clubId) {
            $this->createScope($credit, 'club', $clubId);
        }

        foreach ($rules->allowedServiceIds as $serviceId) {
            $this->createScope($credit, 'service', $serviceId);
        }
    }

    public function copyScopes(WalletCredit $source, WalletCredit $destination): void
    {
        foreach ($source->scopes as $scope) {
            $this->createScope($destination, $scope->scope_type, $scope->scope_id);
        }
    }

    protected function createScope(WalletCredit $credit, string $type, int $id): void
    {
        $scope = ConfiguredModels::newCreditScope();
        $scope->fill([
            'wallet_credit_id' => $credit->id,
            'scope_type' => $type,
            'scope_id' => $id,
        ]);
        $scope->save();
    }

    public function increaseRemaining(WalletCredit $credit, int $amount): void
    {
        if ($amount <= 0) {
            throw new WalletException('Increase amount must be greater than zero.');
        }

        $creditClass = ConfiguredModels::credit();

        /** @var WalletCredit $fresh */
        $fresh = $creditClass::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

        if ($fresh->remaining_amount + $amount > $fresh->original_amount) {
            throw new WalletException(
                "Cannot restore {$amount} to credit #{$credit->id}: remaining would exceed original_amount."
            );
        }

        $fresh->remaining_amount += $amount;
        $fresh->save();

        $credit->setRawAttributes($fresh->getAttributes(), true);
    }

    public function decreaseRemaining(WalletCredit $credit, int $amount): void
    {
        if ($amount <= 0) {
            throw new WalletException('Decrease amount must be greater than zero.');
        }

        $creditClass = ConfiguredModels::credit();

        $affected = $creditClass::query()
            ->whereKey($credit->id)
            ->where('remaining_amount', '>=', $amount)
            ->decrement('remaining_amount', $amount);

        if ($affected === 0) {
            throw InsufficientBalance::forAmount($amount, (int) $credit->fresh()->remaining_amount);
        }

        $credit->refresh();
    }
}
