<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 20 of TEST-SCENARIOS.md: "Negative balance".
 */
class NegativeBalanceTest extends TestCase
{
    /** N001 */
    public function test_negative_balance_is_forbidden_by_default(): void
    {
        $user = User::create(['name' => 'Alice']);

        $this->expectException(InsufficientBalance::class);
        $user->pay(100_000);
    }

    /** N002 */
    public function test_owner_override_allows_negative_without_a_fake_credit(): void
    {
        $user = User::create(['name' => 'Alice']);

        $payment = $user->pay(100_000, ['allow_negative' => true]);

        $this->assertSame(-100_000, $user->balance());
        $this->assertSame(0, $user->wallet()->credits()->count());
        $this->assertSame(0, $payment->allocations()->count());
    }

    /** N003 */
    public function test_negative_policy_does_not_make_a_non_cashable_credit_withdrawable(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(200_000, ['rules' => ['cash_withdrawable' => false]]);

        // Negative is allowed, but the non-cashable credit must never be
        // treated as spendable cash: the deduct's amount is recorded on
        // the ledger without ever consuming the restricted credit, so the
        // wallet's overall balance simply moves down by the deducted
        // amount (200_000 - 100_000 = 100_000) while the credit itself is
        // left completely untouched.
        $deduct = $user->deduct(100_000, ['allow_negative' => true]);

        $this->assertSame(100_000, $deduct->amount);
        $this->assertSame(100_000, $user->balance());
        $this->assertSame(0, $deduct->allocations()->count()); // no allocation against the non-cashable credit

        $credit = $user->wallet()->credits()->first();
        $this->assertSame(200_000, $credit->remaining_amount); // untouched
    }
}
