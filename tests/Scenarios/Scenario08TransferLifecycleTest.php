<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 08 — Transfer Lifecycle
 *
 * @see Scenario08_TransferLifecycle.md
 */
class Scenario08TransferLifecycleTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $reza = User::create(['name' => 'Reza']);

        $ali->charge(1_000_000);
        $reza->charge(200_000);

        $this->assertSame(1_000_000, $ali->balance());
        $this->assertSame(200_000, $reza->balance());
        $this->assertSame(1_200_000, $this->globalMoneyFromTransactions());

        // --- عملیات 1: Transfer Ali → Reza 400,000 ---
        $result = $ali->transferTo($reza, 400_000);
        $this->assertSame(600_000, $ali->balance());
        $this->assertSame(600_000, $reza->balance());
        $this->assertSame(1_200_000, $this->globalMoneyFromTransactions(), 'Transfer must not create or destroy money.');

        $rezaTransferCredit = $this->creditsOf($reza)->last();
        $this->assertNotNull($rezaTransferCredit->parent_credit_id);
        $this->assertSame($ali->wallet()->id, (int) $rezaTransferCredit->source_wallet_id);
        $this->assertSame(400_000, (int) $rezaTransferCredit->remaining_amount);
        $this->assertSame(
            (int) $result->sourceTransaction->amount,
            (int) $result->destinationTransaction->amount
        );

        // --- عملیات 2: Reza Payment 250,000 ---
        $reza->pay(250_000);
        $this->assertSame(350_000, $reza->balance());
        $this->assertSame(600_000, $ali->balance());
        $this->assertSame(950_000, $this->globalMoneyFromTransactions());

        // --- عملیات 3: Ali Payment 100,000 ---
        $ali->pay(100_000);
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(350_000, $reza->balance());

        $finalGlobal = $ali->balance() + $reza->balance();
        $this->assertSame(850_000, $finalGlobal);
        $this->assertSame(1_200_000 - 350_000, $finalGlobal);
        $this->assertAllCreditsInvariant($ali);
        $this->assertAllCreditsInvariant($reza);
    }
}
