<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 13 — Complex Real World Lifecycle
 *
 * @see Scenario13_ComplexWalletLifecycle.md
 */
class Scenario13ComplexWalletLifecycleTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $reza = User::create(['name' => 'Reza']);
        $company = Organization::create(['name' => 'Company A']);

        $ali->charge(500_000);
        $company->charge(1_000_000);
        $company->grantTo($ali, 1_000_000);
        $reza->charge(200_000);

        $initial = 1_700_000;
        $this->assertSame(1_500_000, $ali->balance());
        $this->assertSame(200_000, $reza->balance());
        $this->assertSame($initial, $this->globalMoneyFromTransactions());

        $aliPersonal = $this->creditsOf($ali)[0];
        $aliOrg = $this->creditsOf($ali)[1];
        $this->assertNotNull($aliOrg->parent_credit_id);

        // --- مرحله 1: Ali Payment #1 = 600,000 ---
        $payment1 = $ali->pay(600_000);
        $this->assertPaymentAllocations($payment1, [
            (int) $aliPersonal->id => 500_000,
            (int) $aliOrg->id => 100_000,
        ]);
        $this->assertSame(900_000, $ali->balance());
        $this->assertSame(1_100_000, $this->globalMoneyFromTransactions());

        // --- مرحله 2: Partial Refund #1 = 200,000 ---
        $ali->refund($payment1, 200_000);
        $this->assertSame(1_100_000, $ali->balance());
        $this->assertSame(1_300_000, $this->globalMoneyFromTransactions());
        $this->assertSame(200_000, (int) $aliPersonal->fresh()->remaining_amount);
        $this->assertSame(900_000, (int) $aliOrg->fresh()->remaining_amount);
        $this->assertNotNull($aliOrg->fresh()->parent_credit_id);

        // --- مرحله 3: Transfer Ali → Reza 300,000 ---
        $transfer = $ali->transferTo($reza, 300_000);
        $this->assertSame(800_000, $ali->balance());
        $this->assertSame(500_000, $reza->balance());
        $this->assertSame(1_300_000, $this->globalMoneyFromTransactions(), 'Transfer must not change global total.');
        $this->assertSame(
            (int) $transfer->sourceTransaction->amount,
            (int) $transfer->destinationTransaction->amount
        );

        foreach ($this->creditsOf($reza)->filter(fn ($c) => $c->parent_credit_id) as $transferred) {
            $this->assertSame($ali->wallet()->id, (int) $transferred->source_wallet_id);
        }

        // --- مرحله 4: Reza Payment #2 = 350,000 ---
        $payment2 = $reza->pay(350_000);
        $this->assertSame(150_000, $reza->balance());
        $this->assertSame(800_000, $ali->balance());
        $this->assertSame(950_000, $this->globalMoneyFromTransactions());
        $this->assertSame((int) $payment2->amount, (int) array_sum($this->allocationAmountsByCredit($payment2)));

        // --- مرحله 5: Ali Payment #3 = 250,000 ---
        $payment3 = $ali->pay(250_000);
        $this->assertSame(550_000, $ali->balance());
        $this->assertSame(150_000, $reza->balance());
        $this->assertSame(700_000, $this->globalMoneyFromTransactions());
        $this->assertSame((int) $payment3->amount, (int) array_sum($this->allocationAmountsByCredit($payment3)));

        // --- مرحله 6: Refund #2 on Reza payment = 100,000 ---
        $reza->refund($payment2, 100_000);
        $this->assertSame(250_000, $reza->balance());
        $this->assertSame(550_000, $ali->balance());

        $finalGlobal = $ali->balance() + $reza->balance();
        $this->assertSame(800_000, $finalGlobal);

        // Independent reconciliation from the story ledger:
        // Initial − P1 + R1 ± Transfer0 − P2 − P3 + R2
        $reconciled = $initial - 600_000 + 200_000 - 350_000 - 250_000 + 100_000;
        $this->assertSame(800_000, $reconciled);
        $this->assertSame($reconciled, $finalGlobal);
        $this->assertSame($reconciled, $this->globalMoneyFromTransactions());

        $this->assertAllCreditsInvariant($ali);
        $this->assertAllCreditsInvariant($reza);
    }
}
