<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 04 — Restricted Club Credit
 *
 * @see Scenario04_ClubRestrictedCredit.md
 */
class Scenario04ClubRestrictedCreditTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(500_000, ['rules' => ['allowed_club_ids' => [self::CLUB_A]]]); // A
        $ali->charge(700_000); // B unrestricted

        $this->assertSame(1_200_000, $ali->balance());
        $this->assertCreditRemainings($ali, [500_000, 700_000]);

        // --- عملیات 1: Club A Payment 800,000 → A 500k + B 300k ---
        $payment1 = $ali->pay(800_000, ['club_id' => self::CLUB_A]);
        $this->assertPaymentAllocationsInCreditOrder($payment1, $ali, [500_000, 300_000]);
        $this->assertCreditRemainings($ali, [0, 400_000]);
        $this->assertSame(400_000, $ali->balance());

        // --- عملیات 2: Club B Payment 300,000 → only B ---
        $payment2 = $ali->pay(300_000, ['club_id' => self::CLUB_B]);
        $this->assertPaymentAllocationsInCreditOrder($payment2, $ali, [0, 300_000]);
        $this->assertCreditRemainings($ali, [0, 100_000]);
        $this->assertSame(100_000, $ali->balance());

        // --- عملیات 3: Club A Payment 200,000 must fail (usable = 100k from B only) ---
        $before = $this->snapshotFinancialState();

        try {
            $ali->pay(200_000, ['club_id' => self::CLUB_A]);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertStateUnchanged($before);
        $this->assertCreditRemainings($ali, [0, 100_000]);
        $this->assertSame(100_000, $ali->balance());
        $this->assertAllCreditsInvariant($ali);
    }
}
