<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 05 — Service Restricted Credit
 *
 * @see Scenario05_ServiceRestrictedCredit.md
 */
class Scenario05ServiceRestrictedCreditTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(400_000, [
            'rules' => ['allowed_service_ids' => [self::SERVICE_SWIMMING]],
        ]);
        $ali->charge(600_000);

        $this->assertSame(1_000_000, $ali->balance());
        $this->assertCreditRemainings($ali, [400_000, 600_000]);

        // --- عملیات 1: Swimming 500,000 ---
        $payment1 = $ali->pay(500_000, [
            'segments' => [[
                'amount' => 500_000,
                'scopes' => ['service' => [self::SERVICE_SWIMMING]],
            ]],
        ]);
        $this->assertPaymentAllocationsInCreditOrder($payment1, $ali, [400_000, 100_000]);
        $this->assertCreditRemainings($ali, [0, 500_000]);
        $this->assertSame(500_000, $ali->balance());

        // --- عملیات 2: Gym 300,000 (only B) ---
        $payment2 = $ali->pay(300_000, [
            'segments' => [[
                'amount' => 300_000,
                'scopes' => ['service' => [self::SERVICE_GYM]],
            ]],
        ]);
        $this->assertPaymentAllocationsInCreditOrder($payment2, $ali, [0, 300_000]);
        $this->assertCreditRemainings($ali, [0, 200_000]);
        $this->assertSame(200_000, $ali->balance());

        // --- عملیات 3: Swimming 250,000 must fail ---
        $before = $this->snapshotFinancialState();

        try {
            $ali->pay(250_000, [
                'segments' => [[
                    'amount' => 250_000,
                    'scopes' => ['service' => [self::SERVICE_SWIMMING]],
                ]],
            ]);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertStateUnchanged($before);
        $this->assertCreditRemainings($ali, [0, 200_000]);
        $this->assertSame(200_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
