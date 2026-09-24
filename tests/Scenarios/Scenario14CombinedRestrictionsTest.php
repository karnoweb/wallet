<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 14 — Combined Club + Service Restrictions
 *
 * @see Scenario14_CombinedRestrictions.md
 */
class Scenario14CombinedRestrictionsTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        // A: Club A + Swimming
        $ali->charge(300_000, ['rules' => [
            'allowed_club_ids' => [self::CLUB_A],
            'allowed_service_ids' => [self::SERVICE_SWIMMING],
        ]]);
        // B: Club A + unrestricted service
        $ali->charge(400_000, ['rules' => [
            'allowed_club_ids' => [self::CLUB_A],
        ]]);
        // C: unrestricted club + Swimming
        $ali->charge(500_000, ['rules' => [
            'allowed_service_ids' => [self::SERVICE_SWIMMING],
        ]]);
        // D: fully unrestricted
        $ali->charge(600_000);

        $this->assertSame(1_800_000, $ali->balance());
        $this->assertCreditRemainings($ali, [300_000, 400_000, 500_000, 600_000]);

        $credits = $this->creditsOf($ali);
        [$creditA, $creditB, $creditC, $creditD] = [$credits[0], $credits[1], $credits[2], $credits[3]];

        // --- Eligible balances (intersection) ---
        $this->assertSame(1_800_000, $ali->spendableBalance([
            'club_id' => self::CLUB_A,
            'scopes' => ['service' => [self::SERVICE_SWIMMING]],
        ]), 'Club A + Swimming: A+B+C+D');

        $this->assertSame(1_000_000, $ali->spendableBalance([
            'club_id' => self::CLUB_A,
            'scopes' => ['service' => [self::SERVICE_GYM]],
        ]), 'Club A + Gym: B+D');

        $this->assertSame(1_100_000, $ali->spendableBalance([
            'club_id' => self::CLUB_B,
            'scopes' => ['service' => [self::SERVICE_SWIMMING]],
        ]), 'Club B + Swimming: C+D');

        $this->assertSame(600_000, $ali->spendableBalance([
            'club_id' => self::CLUB_B,
            'scopes' => ['service' => [self::SERVICE_GYM]],
        ]), 'Club B + Gym: D only');

        // --- Payment Club A + Swimming 200,000 → FIFO starts at A ---
        $p1 = $ali->pay(200_000, [
            'club_id' => self::CLUB_A,
            'segments' => [['amount' => 200_000, 'scopes' => ['service' => [self::SERVICE_SWIMMING]]]],
        ]);
        $this->assertPaymentAllocations($p1, [(int) $creditA->id => 200_000]);
        $this->assertCreditRemainings($ali, [100_000, 400_000, 500_000, 600_000]);

        // --- Payment Club A + Gym 500,000 → B 400 + D 100 ---
        $p2 = $ali->pay(500_000, [
            'club_id' => self::CLUB_A,
            'segments' => [['amount' => 500_000, 'scopes' => ['service' => [self::SERVICE_GYM]]]],
        ]);
        $this->assertPaymentAllocations($p2, [
            (int) $creditB->id => 400_000,
            (int) $creditD->id => 100_000,
        ]);
        $this->assertCreditRemainings($ali, [100_000, 0, 500_000, 500_000]);

        // --- Payment Club B + Swimming 600,000 → C 500 + D 100 ---
        $p3 = $ali->pay(600_000, [
            'club_id' => self::CLUB_B,
            'segments' => [['amount' => 600_000, 'scopes' => ['service' => [self::SERVICE_SWIMMING]]]],
        ]);
        $this->assertPaymentAllocations($p3, [
            (int) $creditC->id => 500_000,
            (int) $creditD->id => 100_000,
        ]);
        $this->assertCreditRemainings($ali, [100_000, 0, 0, 400_000]);

        // --- Payment Club B + Gym 400,000 → D only ---
        $p4 = $ali->pay(400_000, [
            'club_id' => self::CLUB_B,
            'segments' => [['amount' => 400_000, 'scopes' => ['service' => [self::SERVICE_GYM]]]],
        ]);
        $this->assertPaymentAllocations($p4, [(int) $creditD->id => 400_000]);
        $this->assertCreditRemainings($ali, [100_000, 0, 0, 0]);
        $this->assertSame(100_000, $ali->balance());

        // Remaining 100k on A is only usable for Club A + Swimming
        $this->assertSame(100_000, $ali->spendableBalance([
            'club_id' => self::CLUB_A,
            'scopes' => ['service' => [self::SERVICE_SWIMMING]],
        ]));
        $this->assertSame(0, $ali->spendableBalance([
            'club_id' => self::CLUB_B,
            'scopes' => ['service' => [self::SERVICE_GYM]],
        ]));

        $this->assertAllCreditsInvariant($ali);
    }
}
