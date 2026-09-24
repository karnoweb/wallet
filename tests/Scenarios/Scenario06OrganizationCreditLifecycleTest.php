<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 06 — Organization Credit Lifecycle
 *
 * FIFO: personal charge is created before the grant, so personal is
 * consumed first on payment.
 *
 * @see Scenario06_OrganizationCreditLifecycle.md
 */
class Scenario06OrganizationCreditLifecycleTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $company = Organization::create(['name' => 'Company A']);

        $ali->charge(300_000); // Credit Personal
        $company->charge(700_000);
        $company->grantTo($ali, 700_000); // Credit Organization (lineage to company)

        $this->assertSame(1_000_000, $ali->balance());
        $this->assertCreditRemainings($ali, [300_000, 700_000]);

        $personal = $this->creditsOf($ali)[0];
        $organizational = $this->creditsOf($ali)[1];
        $this->assertNull($personal->parent_credit_id);
        $this->assertNotNull($organizational->parent_credit_id);
        $this->assertSame($company->wallet()->id, (int) $organizational->source_wallet_id);

        // --- عملیات 1: Payment 800,000 → Personal 300k + Org 500k ---
        $payment = $ali->pay(800_000);
        $this->assertPaymentAllocationsInCreditOrder($payment, $ali, [300_000, 500_000]);
        $this->assertCreditRemainings($ali, [0, 200_000]);
        $this->assertSame(200_000, $ali->balance());
        $this->assertRefundable($payment, 800_000);

        // --- عملیات 2: Partial Refund 300,000 (restores personal allocation fully) ---
        $ali->refund($payment, 300_000);
        $this->assertCreditRemainings($ali, [300_000, 200_000]);
        $this->assertSame(500_000, $ali->balance());
        $this->assertRefundable($payment, 500_000);
        $this->assertCount(2, $this->creditsOf($ali), 'Refund must not create a new unrestricted credit.');
        $this->assertSame(
            (int) $organizational->id,
            (int) $this->creditsOf($ali)[1]->id,
            'Organizational credit identity must be preserved after refund.'
        );
        $this->assertNotNull($this->creditsOf($ali)[1]->parent_credit_id);

        // --- عملیات 3: Payment 400,000 → Personal 300k + Org 100k ---
        $payment2 = $ali->pay(400_000);
        $this->assertPaymentAllocationsInCreditOrder($payment2, $ali, [300_000, 100_000]);
        $this->assertCreditRemainings($ali, [0, 100_000]);
        $this->assertSame(100_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
