<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 09 — Transfer Then Refund
 *
 * @see Scenario09_TransferAndRefund.md
 */
class Scenario09TransferAndRefundTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $reza = User::create(['name' => 'Reza']);

        $ali->charge(1_000_000);
        $aliCredit = $this->creditsOf($ali)->first();

        $ali->transferTo($reza, 600_000);
        $this->assertSame(400_000, $ali->balance());
        $this->assertSame(600_000, $reza->balance());

        $rezaCredit = $this->creditsOf($reza)->first();
        $this->assertSame((int) $aliCredit->id, (int) $rezaCredit->parent_credit_id);
        $this->assertSame($ali->wallet()->id, (int) $rezaCredit->source_wallet_id);

        $payment = $reza->pay(500_000);
        $this->assertSame(100_000, $reza->balance());
        $this->assertSame(100_000, (int) $rezaCredit->fresh()->remaining_amount);

        $reza->refund($payment, 200_000);
        $this->assertSame(300_000, $reza->balance());
        $rezaCredit->refresh();
        $this->assertSame(300_000, (int) $rezaCredit->remaining_amount);
        $this->assertSame((int) $aliCredit->id, (int) $rezaCredit->parent_credit_id, 'Lineage must survive refund.');
        $this->assertCount(1, $this->creditsOf($reza), 'Refund restores the transferred credit; no new credit.');

        $this->assertSame(400_000, $ali->balance());
        $this->assertSame(300_000, $reza->balance());
        $this->assertSame(700_000, $ali->balance() + $reza->balance());
        $this->assertSame(1_000_000 - 300_000, $ali->balance() + $reza->balance());
        $this->assertAllCreditsInvariant($ali);
        $this->assertAllCreditsInvariant($reza);
    }
}
