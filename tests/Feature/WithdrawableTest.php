<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 13 of TEST-SCENARIOS.md: "Withdrawable rule".
 */
class WithdrawableTest extends TestCase
{
    /** WD001 */
    public function test_normal_charge_is_fully_cashable_by_default(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $this->assertSame(500_000, $user->withdrawableBalance());
    }

    /** WD002 */
    public function test_non_cashable_grant_is_excluded_from_withdrawable_balance(): void
    {
        $organization = \Karnoweb\Wallet\Tests\Support\Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);

        $organization->charge(1_000_000);
        $organization->grantTo($employee, 1_000_000, ['cash_withdrawable' => false]);

        $this->assertSame(1_000_000, $employee->balance());
        $this->assertSame(0, $employee->withdrawableBalance());
    }

    /** WD003 */
    public function test_deduct_fails_when_only_non_cashable_credit_exists(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(1_000_000, ['rules' => ['cash_withdrawable' => false]]);

        $this->expectException(InsufficientBalance::class);
        $user->deduct(100_000);
    }

    /** WD004 */
    public function test_deduct_only_consumes_the_cashable_portion(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, ['rules' => ['cash_withdrawable' => false]]); // first, non-cashable
        $user->charge(500_000); // second, cashable

        $deduct = $user->deduct(300_000);

        $this->assertSame(300_000, $deduct->amount);

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(1_000_000, $credits[0]->remaining_amount); // untouched
        $this->assertSame(200_000, $credits[1]->remaining_amount);
    }
}
