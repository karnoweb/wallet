<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 11 — Idempotency Full Lifecycle
 *
 * @see Scenario11_IdempotencyLifecycle.md
 */
class Scenario11IdempotencyLifecycleTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ali->charge(1_000_000);
        $this->assertSame(1_000_000, $ali->balance());

        // --- عملیات 1 ---
        $payment1 = $ali->pay(300_000, ['idempotency_key' => 'payment-001']);
        $this->assertSame(700_000, $ali->balance());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());

        // --- عملیات 2: exact retry ---
        $replay1 = $ali->pay(300_000, ['idempotency_key' => 'payment-001']);
        $this->assertSame($payment1->id, $replay1->id);
        $this->assertSame(700_000, $ali->balance());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());

        // --- عملیات 3 ---
        $payment2 = $ali->pay(200_000, ['idempotency_key' => 'payment-002']);
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(2, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());

        // --- عملیات 4: retry payment-002 ---
        $replay2 = $ali->pay(200_000, ['idempotency_key' => 'payment-002']);
        $this->assertSame($payment2->id, $replay2->id);
        $this->assertSame(500_000, $ali->balance());
        $this->assertSame(2, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());

        $this->assertSame(500_000, 1_000_000 - 500_000);
        $this->assertAllCreditsInvariant($ali);
    }
}
