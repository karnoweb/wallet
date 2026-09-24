<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 03 — Multi Credit Refund Restoration
 *
 * Contract: partial refund restores consume allocations in original
 * allocation id order (FIFO of the payment's allocations), NOT reverse.
 *
 * @see Scenario03_MultiCreditRefund.md
 */
class Scenario03MultiCreditRefundTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(300_000);
        $ali->charge(500_000);
        $ali->charge(700_000);
        $this->assertSame(1_500_000, $ali->balance());

        // --- عملیات 1: Payment 600,000 ---
        $payment = $ali->pay(600_000);
        $this->assertPaymentAllocationsInCreditOrder($payment, $ali, [300_000, 300_000, 0]);
        $this->assertCreditRemainings($ali, [0, 200_000, 700_000]);
        $this->assertSame(900_000, $ali->balance());
        $this->assertRefundable($payment, 600_000);

        // --- عملیات 2: Refund 200,000 (restores Credit A first by allocation id) ---
        $ali->refund($payment, 200_000);
        // A was fully consumed (300k); restore 200k → A=200k; B unchanged at 200k
        $this->assertCreditRemainings($ali, [200_000, 200_000, 700_000]);
        $this->assertSame(1_100_000, $ali->balance());
        $this->assertRefundable($payment, 400_000);

        $credits = $this->creditsOf($ali);
        $this->assertNull($credits[0]->parent_credit_id, 'Refund must restore original credit, not create a new one.');
        $this->assertCount(3, $credits, 'No new credit row may be created by refund.');

        // --- عملیات 3: Refund 400,000 (remaining of A then all of B) ---
        $ali->refund($payment, 400_000);
        $this->assertCreditRemainings($ali, [300_000, 500_000, 700_000]);
        $this->assertSame(1_500_000, $ali->balance());
        $this->assertRefundable($payment, 0);
        $this->assertCount(3, $this->creditsOf($ali));
        $this->assertAllCreditsInvariant($ali);
    }
}
