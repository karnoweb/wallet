<?php

namespace Karnoweb\Wallet\Tests\Scenarios;

use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;

/**
 * Scenario 15 — Transaction & Allocation Integrity
 *
 * @see Scenario15_TransactionAllocationIntegrity.md
 */
class Scenario15TransactionAllocationIntegrityTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $ali->charge(200_000);
        $ali->charge(300_000);
        $ali->charge(500_000);
        $this->assertCreditRemainings($ali, [200_000, 300_000, 500_000]);

        $payment = $ali->pay(750_000);
        $this->assertPaymentAllocationsInCreditOrder($payment, $ali, [200_000, 300_000, 250_000]);
        $this->assertSame(750_000, (int) array_sum($this->allocationAmountsByCredit($payment)));
        $this->assertCreditRemainings($ali, [0, 0, 250_000]);
        $this->assertSame(250_000, $ali->balance());

        $consumeIds = $this->consumeAllocations($payment)->pluck('id');
        $this->assertCount(3, $consumeIds);

        foreach ($this->consumeAllocations($payment) as $allocation) {
            $this->assertSame($payment->id, (int) $allocation->wallet_transaction_id);
            $this->assertNotNull($allocation->credit);
            $this->assertSame(WalletAllocationType::Consume, $allocation->type);
        }

        $refund = $ali->refund($payment, 350_000);
        $this->assertSame(600_000, $ali->balance());
        $this->assertRefundable($payment, 400_000);

        // Restore allocations link back to original consumes and the refund TX
        $restores = ConfiguredModels::allocation()::query()
            ->where('wallet_transaction_id', $refund->id)
            ->where('type', WalletAllocationType::Restore->value)
            ->orderBy('id')
            ->get();

        $this->assertNotEmpty($restores);
        $this->assertSame(350_000, (int) $restores->sum('amount'));

        foreach ($restores as $restore) {
            $this->assertSame($refund->id, (int) $restore->wallet_transaction_id);
            $this->assertNotNull($restore->original_allocation_id);
            $this->assertTrue($consumeIds->contains($restore->original_allocation_id));
            $this->assertNotNull($restore->credit);
            $this->assertSame(WalletAllocationType::Restore, $restore->type);
        }

        // No orphan allocations: every allocation belongs to an existing transaction
        $orphanAllocations = ConfiguredModels::allocation()::query()
            ->whereNotIn(
                'wallet_transaction_id',
                ConfiguredModels::transaction()::query()->pluck('id')
            )
            ->count();
        $this->assertSame(0, $orphanAllocations);

        // No orphan transactions without operation when created via package flows
        $this->assertSame(
            ConfiguredModels::transaction()::query()->count(),
            ConfiguredModels::transaction()::query()->whereNotNull('operation_id')->count()
        );

        $this->assertAllCreditsInvariant($ali);
    }
}
