<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Contracts\CreditSelectionStrategy;
use Karnoweb\Wallet\DTOs\SpendSegment;
use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletAllocation;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Creates the immutable WalletAllocation rows that link a debit
 * transaction to the credit(s) it consumed, or a refund/return
 * transaction to the allocation(s) it restores. Also mutates
 * `WalletCredit::remaining_amount`, the only field a credit is allowed to
 * have mutated by package runtime logic.
 */
class AllocationService
{
    public function __construct(protected CreditService $credits)
    {
    }

    /**
     * @param  Collection<int, array{credit: \Karnoweb\Wallet\Models\WalletCredit, amount: int}>  $plan
     * @return Collection<int, WalletAllocation>
     */
    public function consume(WalletTransaction $transaction, Collection $plan, ?string $segmentKey = null): Collection
    {
        return $plan->map(function (array $entry) use ($transaction, $segmentKey) {
            $credit = $entry['credit'];
            $amount = $entry['amount'];

            $allocation = ConfiguredModels::newAllocation();
            $allocation->fill([
                'wallet_transaction_id' => $transaction->id,
                'wallet_credit_id' => $credit->id,
                'amount' => $amount,
                'type' => WalletAllocationType::Consume->value,
                'segment_key' => $segmentKey,
            ]);
            $allocation->save();

            $this->credits->decreaseRemaining($credit, $amount);

            return $allocation;
        })->values();
    }

    /**
     * Determine eligible credits for one spend segment, lock the
     * candidate rows, re-check eligibility/remaining_amount after the
     * lock, plan a FIFO (or configured strategy) allocation and consume
     * it. Implements blueprint section 18 steps 4-11 for a single
     * segment.
     *
     * @return array{allocations: Collection<int, WalletAllocation>, plan: Collection<int, array{credit: \Karnoweb\Wallet\Models\WalletCredit, amount: int}>, covered: int, shortfall: int}
     */
    public function allocateSegment(
        Wallet $wallet,
        WalletTransaction $transaction,
        SpendSegment $segment,
        ?int $clubId,
        bool $requireCashWithdrawable,
        CreditSelectionStrategy $strategy,
        \DateTimeInterface $at,
        bool $allowNegative
    ): array {
        $serviceIds = $segment->scopes['service'] ?? [];

        $candidates = $this->credits->eligibleCandidates($wallet, $clubId, $serviceIds, $requireCashWithdrawable, $at);

        $locked = $this->credits->lockCredits($candidates->pluck('id')->all());

        // Scope rows are immutable once created, so it is safe to reuse
        // the scopes already eager-loaded on the (unlocked) candidates
        // instead of re-querying them for the locked rows.
        $scopesByCreditId = $candidates->keyBy('id')->map(fn ($credit) => $credit->scopes);

        $eligible = $locked
            ->map(function ($credit) use ($scopesByCreditId) {
                if ($scopesByCreditId->has($credit->id)) {
                    $credit->setRelation('scopes', $scopesByCreditId->get($credit->id));
                }

                return $credit;
            })
            ->filter(fn ($credit) => $this->credits->isEligible($credit, $clubId, $serviceIds, $requireCashWithdrawable, $at))
            ->values();

        $plan = $strategy->plan($eligible, $segment->amount);

        $covered = (int) $plan->sum('amount');
        $shortfall = $segment->amount - $covered;

        if ($shortfall > 0 && ! $allowNegative) {
            throw InsufficientBalance::forAmount($segment->amount, $covered);
        }

        $allocations = $this->consume($transaction, $plan, $segment->key);

        return [
            'allocations' => $allocations,
            'plan' => $plan,
            'covered' => $covered,
            'shortfall' => max($shortfall, 0),
        ];
    }

    /**
     * Like {@see allocateSegment()} but ignores club/service scope
     * restrictions entirely. Used by Transfer/Grant: emptying value out
     * of a wallet into another wallet is not "spending for a purpose",
     * so every remaining, date-valid (and optionally cash-withdrawable)
     * credit is a candidate regardless of its club/service scopes. The
     * scopes are preserved on the destination credit instead.
     *
     * @return array{allocations: Collection<int, WalletAllocation>, plan: Collection<int, array{credit: \Karnoweb\Wallet\Models\WalletCredit, amount: int}>, covered: int, shortfall: int}
     */
    public function allocateUnscoped(
        Wallet $wallet,
        WalletTransaction $transaction,
        int $amount,
        bool $requireCashWithdrawable,
        CreditSelectionStrategy $strategy,
        \DateTimeInterface $at,
        bool $allowNegative,
        ?string $segmentKey = null
    ): array {
        $candidates = $this->credits->unscopedEligibleCandidates($wallet, $requireCashWithdrawable, $at);

        $locked = $this->credits->lockCredits($candidates->pluck('id')->all());

        $scopesByCreditId = $candidates->keyBy('id')->map(fn ($credit) => $credit->scopes);

        $eligible = $locked
            ->map(function ($credit) use ($scopesByCreditId) {
                if ($scopesByCreditId->has($credit->id)) {
                    $credit->setRelation('scopes', $scopesByCreditId->get($credit->id));
                }

                return $credit;
            })
            ->filter(fn ($credit) => $this->credits->unscopedIsEligible($credit, $requireCashWithdrawable, $at))
            ->values();

        $plan = $strategy->plan($eligible, $amount);

        $covered = (int) $plan->sum('amount');
        $shortfall = $amount - $covered;

        if ($shortfall > 0 && ! $allowNegative) {
            throw InsufficientBalance::forAmount($amount, $covered);
        }

        $allocations = $this->consume($transaction, $plan, $segmentKey);

        return [
            'allocations' => $allocations,
            'plan' => $plan,
            'covered' => $covered,
            'shortfall' => max($shortfall, 0),
        ];
    }

    public function restore(WalletTransaction $transaction, WalletAllocation $originalAllocation, int $amount): WalletAllocation
    {
        $allocation = ConfiguredModels::newAllocation();
        $allocation->fill([
            'wallet_transaction_id' => $transaction->id,
            'wallet_credit_id' => $originalAllocation->wallet_credit_id,
            'amount' => $amount,
            'type' => WalletAllocationType::Restore->value,
            'original_allocation_id' => $originalAllocation->id,
            'segment_key' => $originalAllocation->segment_key,
        ]);
        $allocation->save();

        $this->credits->increaseRemaining($originalAllocation->credit, $amount);

        return $allocation;
    }
}
