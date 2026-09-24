<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 12 of TEST-SCENARIOS.md: "Start/end date rules".
 */
class CreditDateTest extends TestCase
{
    /** D001 */
    public function test_future_credit_is_excluded_from_spendable_balance_and_payment(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['rules' => ['starts_at' => now()->addDay()]]);

        $this->assertSame(0, $user->spendableBalance());
        $this->assertSame(500_000, $user->balance());

        $this->expectException(InsufficientBalance::class);
        $user->pay(100_000);
    }

    /** D002 */
    public function test_active_credit_between_starts_and_expires_is_eligible(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['rules' => [
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]]);

        $this->assertSame(500_000, $user->spendableBalance());

        $payment = $user->pay(100_000);
        $this->assertSame(100_000, $payment->amount);
    }

    /** D003 */
    public function test_expired_credit_is_excluded_from_spendable_balance_before_expiration_job_runs(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['rules' => ['expires_at' => now()->subMinute()]]);

        $this->assertSame(0, $user->spendableBalance());
        $this->assertSame(500_000, $user->balance());

        $this->expectException(InsufficientBalance::class);
        $user->pay(100_000);
    }
}
