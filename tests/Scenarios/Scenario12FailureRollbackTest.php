<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Exceptions\InvalidRefund;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 12 — Failed Operations Do Not Change State
 *
 * @see Scenario12_FailureRollback.md
 */
class Scenario12FailureRollbackTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $reza = User::create(['name' => 'Reza']);

        $ali->charge(500_000);
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(0, $reza->balance());

        // --- عملیات 1: Payment 700,000 fail ---
        $before = $this->snapshotFinancialState();
        try {
            $ali->pay(700_000);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
        }
        $this->assertStateUnchanged($before);
        $this->assertSame(500_000, $ali->balance());

        // --- عملیات 2: Transfer 800,000 fail ---
        $before = $this->snapshotFinancialState();
        try {
            $ali->transferTo($reza, 800_000);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
        }
        $this->assertStateUnchanged($before);
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(0, $reza->balance());

        // --- عملیات 3: valid Payment 300,000 ---
        $payment = $ali->pay(300_000);
        $this->assertSame(200_000, $ali->balance());
        $this->assertRefundable($payment, 300_000);

        // --- عملیات 4: Refund 400,000 > payment fail ---
        $before = $this->snapshotFinancialState();
        try {
            $ali->refund($payment, 400_000);
            $this->fail('Expected InvalidRefund.');
        } catch (InvalidRefund $e) {
        }
        $this->assertStateUnchanged($before);
        $this->assertSame(200_000, $ali->balance());
        $this->assertRefundable($payment, 300_000);
        $this->assertAllCreditsInvariant($ali);
    }
}
