<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 10 of TEST-SCENARIOS.md: "Spendable credit — service rule".
 */
class CreditServiceScopeTest extends TestCase
{
    /** RS001 */
    public function test_credit_allowed_for_its_own_service(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['rules' => ['allowed_service_ids' => [10]]]);

        $payment = $user->pay(200_000, [
            'segments' => [['amount' => 200_000, 'scopes' => ['service' => [10]]]],
        ]);

        $this->assertSame(200_000, $payment->amount);
    }

    /** RS002 */
    public function test_credit_restricted_to_a_service_is_not_eligible_for_another(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['rules' => ['allowed_service_ids' => [10]]]);

        try {
            $user->pay(200_000, [
                'segments' => [['amount' => 200_000, 'scopes' => ['service' => [20]]]],
            ]);
            $this->fail('Expected InsufficientBalance to be thrown.');
        } catch (InsufficientBalance $e) {
            $this->assertInstanceOf(InsufficientBalance::class, $e);
        }

        // The credit restricted to service 10 must remain fully untouched.
        $this->assertSame(500_000, $user->wallet()->credits()->first()->remaining_amount);
    }

    /** RS003 */
    public function test_credit_without_service_scope_is_eligible_for_any_service(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000);

        $payment = $user->pay(200_000, [
            'segments' => [['amount' => 200_000, 'scopes' => ['service' => [999]]]],
        ]);

        $this->assertSame(200_000, $payment->amount);
    }
}
