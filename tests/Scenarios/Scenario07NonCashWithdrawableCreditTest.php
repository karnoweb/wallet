<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 07 — Non Cash Withdrawable Organizational Credit
 *
 * Service payment may consume non-cashable credits; deduct() requires
 * cash_withdrawable=true.
 *
 * @see Scenario07_NonCashWithdrawableCredit.md
 */
class Scenario07NonCashWithdrawableCreditTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $company = Organization::create(['name' => 'Company A']);

        $ali->charge(300_000); // Personal, cashable by default
        $company->charge(700_000);
        $company->grantTo($ali, 700_000, ['cash_withdrawable' => false]);

        $this->assertSame(1_000_000, $ali->balance());
        $this->assertSame(300_000, $ali->withdrawableBalance());
        $this->assertCreditRemainings($ali, [300_000, 700_000]);
        $this->assertTrue((bool) $this->creditsOf($ali)[0]->cash_withdrawable);
        $this->assertFalse((bool) $this->creditsOf($ali)[1]->cash_withdrawable);

        // --- عملیات 1: Service Payment 600,000 (both credits eligible) ---
        $payment = $ali->pay(600_000);
        $this->assertPaymentAllocationsInCreditOrder($payment, $ali, [300_000, 300_000]);
        $this->assertCreditRemainings($ali, [0, 400_000]);
        $this->assertSame(400_000, $ali->balance());
        $this->assertSame(0, $ali->withdrawableBalance());

        // --- عملیات 2: Deduct 400,000 requires cashable → fail ---
        $before = $this->snapshotFinancialState();

        try {
            $ali->deduct(400_000);
            $this->fail('Expected InsufficientBalance for non-cashable-only wallet.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertStateUnchanged($before);
        $this->assertCreditRemainings($ali, [0, 400_000]);
        $this->assertSame(400_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
