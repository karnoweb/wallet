<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 02 — FIFO Multi Credit Payment
 *
 * @see Scenario02_MultiCreditPayment.md
 */
class Scenario02MultiCreditPaymentTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(300_000); // Credit A
        $ali->charge(500_000); // Credit B
        $ali->charge(700_000); // Credit C

        $this->assertSame(1_500_000, $ali->balance());
        $this->assertCreditRemainings($ali, [300_000, 500_000, 700_000]);

        // --- عملیات 1: Payment 600,000 → A 300k + B 300k ---
        $payment1 = $ali->pay(600_000);
        $this->assertPaymentAllocationsInCreditOrder($payment1, $ali, [300_000, 300_000, 0]);
        $this->assertCreditRemainings($ali, [0, 200_000, 700_000]);
        $this->assertSame(900_000, $ali->balance());

        // --- عملیات 2: Payment 500,000 → B 200k + C 300k ---
        $payment2 = $ali->pay(500_000);
        $this->assertPaymentAllocationsInCreditOrder($payment2, $ali, [0, 200_000, 300_000]);
        $this->assertCreditRemainings($ali, [0, 0, 400_000]);
        $this->assertSame(400_000, $ali->balance());

        $this->assertSame(1_500_000 - 1_100_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
