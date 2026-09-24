<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Base for scenario-based multi-step financial stories.
 * Helpers are test-only; scenarios always call the real public API.
 */
abstract class ScenarioTestCase extends TestCase
{
    public const CLUB_A = 1;

    public const CLUB_B = 2;

    public const SERVICE_SWIMMING = 10;

    public const SERVICE_GYM = 20;

    protected function creditsOf(User $user): Collection
    {
        return $user->wallet()->credits()->orderBy('id')->get();
    }

    protected function creditRemaining(User $user, int $index): int
    {
        $credit = $this->creditsOf($user)->values()->get($index);

        $this->assertNotNull($credit, "Expected credit at index {$index}.");

        return (int) $credit->remaining_amount;
    }

    protected function assertCreditRemainings(User $user, array $expectedRemainings): void
    {
        $credits = $this->creditsOf($user)->values();

        $this->assertCount(count($expectedRemainings), $credits, 'Unexpected credit count.');

        foreach ($expectedRemainings as $index => $expected) {
            $credit = $credits[$index];
            $this->assertSame(
                $expected,
                (int) $credit->remaining_amount,
                "Credit #{$index} (id={$credit->id}) remaining mismatch."
            );
            $this->assertCreditInvariant($credit);
        }
    }

    protected function assertCreditInvariant(WalletCredit $credit): void
    {
        $this->assertGreaterThanOrEqual(0, (int) $credit->remaining_amount);
        $this->assertLessThanOrEqual(
            (int) $credit->original_amount,
            (int) $credit->remaining_amount
        );
    }

    protected function assertAllCreditsInvariant(User $user): void
    {
        foreach ($this->creditsOf($user) as $credit) {
            $this->assertCreditInvariant($credit);
        }
    }

    /**
     * @return Collection<int, \Karnoweb\Wallet\Models\WalletAllocation>
     */
    protected function consumeAllocations(WalletTransaction $payment): Collection
    {
        return $payment->allocations()
            ->where('type', WalletAllocationType::Consume->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * Map of wallet_credit_id => consumed amount for a payment.
     *
     * @return array<int, int>
     */
    protected function allocationAmountsByCredit(WalletTransaction $payment): array
    {
        return $this->consumeAllocations($payment)
            ->groupBy('wallet_credit_id')
            ->map(fn ($rows) => (int) $rows->sum('amount'))
            ->all();
    }

    protected function assertPaymentAllocations(WalletTransaction $payment, array $expectedByCreditId): void
    {
        $actual = $this->allocationAmountsByCredit($payment);
        ksort($actual);
        ksort($expectedByCreditId);

        $this->assertSame($expectedByCreditId, $actual, 'Payment allocation map mismatch.');
        $this->assertSame(
            (int) $payment->amount,
            (int) array_sum($actual),
            'SUM(allocations) must equal payment amount.'
        );
    }

    /**
     * Assert allocations in FIFO credit order by listing remainings-before
     * credits in id order with the expected consume amounts.
     *
     * @param  array<int, int>  $expectedAmountsInCreditOrder  e.g. [300000, 300000, 0] for A,B,C
     */
    protected function assertPaymentAllocationsInCreditOrder(
        WalletTransaction $payment,
        User $user,
        array $expectedAmountsInCreditOrder
    ): void {
        $credits = $this->creditsOf($user)->values();
        $expected = [];

        foreach ($expectedAmountsInCreditOrder as $index => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $expected[(int) $credits[$index]->id] = $amount;
        }

        $this->assertPaymentAllocations($payment, $expected);
    }

    protected function refundableAmount(WalletTransaction $payment): int
    {
        $allocationClass = ConfiguredModels::allocation();
        $consumes = $this->consumeAllocations($payment);

        if ($consumes->isEmpty()) {
            return 0;
        }

        $restored = $allocationClass::query()
            ->where('type', WalletAllocationType::Restore->value)
            ->whereIn('original_allocation_id', $consumes->pluck('id'))
            ->sum('amount');

        return (int) $consumes->sum('amount') - (int) $restored;
    }

    protected function assertRefundable(WalletTransaction $payment, int $expected): void
    {
        $this->assertSame($expected, $this->refundableAmount($payment), 'Refundable amount mismatch.');
        $this->assertLessThanOrEqual((int) $payment->amount, (int) $payment->amount - 0);
        $this->assertGreaterThanOrEqual(0, $this->refundableAmount($payment));
        $totalRefunded = (int) $payment->amount - $this->refundableAmount($payment);
        $this->assertLessThanOrEqual((int) $payment->amount, $totalRefunded);
    }

    protected function snapshotFinancialState(): array
    {
        return [
            'operations' => ConfiguredModels::operation()::query()->count(),
            'transactions' => ConfiguredModels::transaction()::query()->count(),
            'allocations' => ConfiguredModels::allocation()::query()->count(),
            'credits' => ConfiguredModels::credit()::query()->count(),
            'credit_remainings' => ConfiguredModels::credit()::query()
                ->orderBy('id')
                ->pluck('remaining_amount', 'id')
                ->map(fn ($v) => (int) $v)
                ->all(),
            'balances' => ConfiguredModels::wallet()::query()
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn ($w) => [$w->id => $w->balance()])
                ->all(),
        ];
    }

    protected function assertStateUnchanged(array $before): void
    {
        $this->assertSame($before, $this->snapshotFinancialState(), 'Failed operation must leave financial state unchanged.');
    }

    protected function globalMoneyFromTransactions(): int
    {
        // Sum of all wallet balances = total transaction net across wallets.
        return (int) ConfiguredModels::wallet()::query()
            ->get()
            ->sum(fn ($w) => $w->balance());
    }
}
