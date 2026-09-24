<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 10 — Credit Expiration Lifecycle
 *
 * Expired credits remain on the ledger (nominal balance) but are not
 * eligible for payment until/unless expiration job burns them.
 *
 * @see Scenario10_CreditExpiration.md
 */
class Scenario10CreditExpirationTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(300_000, ['rules' => [
            'expires_at' => now()->subDay(),
            'expire_action' => 'none',
        ]]);
        $ali->charge(500_000, ['rules' => [
            'expires_at' => now()->addDay(),
            'expire_action' => 'none',
        ]]);
        $ali->charge(700_000);

        $this->assertSame(1_500_000, $ali->balance(), 'Nominal balance includes expired credit.');
        $this->assertSame(1_200_000, $ali->spendableBalance());
        $this->assertCreditRemainings($ali, [300_000, 500_000, 700_000]);

        // --- عملیات 1: Payment 800,000 uses B then C (not A) ---
        $payment = $ali->pay(800_000);
        $this->assertPaymentAllocationsInCreditOrder($payment, $ali, [0, 500_000, 300_000]);
        $this->assertCreditRemainings($ali, [300_000, 0, 400_000]);
        $this->assertSame(700_000, $ali->balance());
        $this->assertSame(400_000, $ali->spendableBalance());

        // --- عملیات 2: Payment 500,000 must fail (usable = 400k) ---
        $before = $this->snapshotFinancialState();

        try {
            $ali->pay(500_000);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertStateUnchanged($before);
        $this->assertCreditRemainings($ali, [300_000, 0, 400_000]);
        $this->assertSame(700_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
