<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 01 — Basic Wallet Lifecycle
 *
 * @see Scenario01_BasicWalletLifecycle.md
 */
class Scenario01BasicWalletLifecycleTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $this->assertSame(0, $ali->balance());

        // --- عملیات 1: Charge 1,000,000 ---
        $ali->charge(1_000_000);
        $this->assertSame(1_000_000, $ali->balance());
        $this->assertCreditRemainings($ali, [1_000_000]);
        $creditA = $this->creditsOf($ali)->first();
        $this->assertSame(1_000_000, (int) $creditA->original_amount);

        // --- عملیات 2: Payment 300,000 ---
        $payment1 = $ali->pay(300_000);
        $this->assertSame(700_000, $ali->balance());
        $this->assertCreditRemainings($ali, [700_000]);
        $this->assertPaymentAllocationsInCreditOrder($payment1, $ali, [300_000]);
        $this->assertRefundable($payment1, 300_000);

        // --- عملیات 3: Partial Refund 100,000 ---
        $ali->refund($payment1, 100_000);
        $this->assertSame(800_000, $ali->balance());
        $this->assertCreditRemainings($ali, [800_000]);
        $this->assertRefundable($payment1, 200_000);

        // --- عملیات 4: Payment 500,000 ---
        $payment2 = $ali->pay(500_000);
        $this->assertSame(300_000, $ali->balance());
        $this->assertCreditRemainings($ali, [300_000]);
        $this->assertPaymentAllocationsInCreditOrder($payment2, $ali, [500_000]);

        // --- عملیات 5: Refund remaining of Payment #1 = 200,000 ---
        $ali->refund($payment1, 200_000);
        $this->assertSame(500_000, $ali->balance());
        $this->assertCreditRemainings($ali, [500_000]);
        $this->assertRefundable($payment1, 0);

        // --- وضعیت نهایی / کنترل حسابداری ---
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(1_000_000 - 500_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
