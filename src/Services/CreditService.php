<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\DTOs\CreditRules;
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
     * eager loading scopes to avoid N+1 checks per candidate.
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

        $candidates = $query->with('scopes')->get();

        return $candidates
            ->filter(fn (WalletCredit $credit) => $this->matchesScopes($credit, $clubId, $serviceIds))
            ->values();
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

        return $query->with('scopes')->get();
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
     * Lock the given credit ids for update so allocation cannot race with
     * another concurrent operation, then re-check eligibility criteria
     * that could have changed since the candidates were selected.
     *
     * @return Collection<int, WalletCredit> Keyed by credit id.
     */
    public function lockCredits(array $creditIds): Collection
    {
        if (empty($creditIds)) {
            return collect();
        }

        $creditClass = ConfiguredModels::credit();

        return $creditClass::query()
            ->whereIn('id', $creditIds)
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
        $credit->remaining_amount += $amount;
        $credit->save();
    }

    public function decreaseRemaining(WalletCredit $credit, int $amount): void
    {
        $credit->remaining_amount -= $amount;
        $credit->save();
    }
}
