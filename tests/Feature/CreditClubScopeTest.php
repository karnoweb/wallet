<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 9 of TEST-SCENARIOS.md: "Spendable credit — club rule".
 */
class CreditClubScopeTest extends TestCase
{
    /** RC001 (section 9) */
    public function test_club_restricted_credit_works_in_its_own_club(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, [
            'club_id' => 1,
            'rules' => ['allowed_club_ids' => [1]],
        ]);

        $payment = $user->pay(300_000, ['club_id' => 1]);

        $this->assertSame(300_000, $payment->amount);
        $this->assertSame(700_000, $user->balance());
    }

    /** RC002 (section 9) */
    public function test_club_restricted_credit_is_blocked_in_another_club(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, [
            'club_id' => 1,
            'rules' => ['allowed_club_ids' => [1]],
        ]);

        try {
            $user->pay(300_000, ['club_id' => 2]);
            $this->fail('Expected InsufficientBalance to be thrown.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertSame(1_000_000, $user->wallet()->credits()->first()->remaining_amount);
    }

    /** RC003 (section 9) */
    public function test_unrestricted_credit_is_usable_in_any_club(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, ['club_id' => 1]);

        $payment = $user->pay(300_000, ['club_id' => 9]);

        $this->assertSame(300_000, $payment->amount);
    }
}
