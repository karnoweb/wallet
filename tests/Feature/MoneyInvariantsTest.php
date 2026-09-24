<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Post-operation money invariants that must always hold.
 */
class MoneyInvariantsTest extends TestCase
{
    public function test_credit_remaining_never_exceeds_original_after_payment_and_refund(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $credit = ConfiguredModels::credit()::query()->first();

        $payment = $user->pay(40_000);
        $credit->refresh();

        $this->assertGreaterThanOrEqual(0, $credit->remaining_amount);
        $this->assertLessThanOrEqual($credit->original_amount, $credit->remaining_amount);
        $this->assertSame(60_000, $credit->remaining_amount);

        $user->refund($payment, 40_000);
        $credit->refresh();

        $this->assertSame(100_000, $credit->remaining_amount);
        $this->assertSame($credit->original_amount, $credit->remaining_amount);
    }

    public function test_payment_allocations_sum_equals_payment_amount(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(30_000);
        $user->charge(70_000);

        $payment = $user->pay(100_000);

        $allocated = (int) ConfiguredModels::allocation()::query()
            ->where('wallet_transaction_id', $payment->id)
            ->where('type', WalletAllocationType::Consume->value)
            ->sum('amount');

        $this->assertSame(100_000, $allocated);
        $this->assertSame(100_000, $payment->amount);
    }

    public function test_total_restores_never_exceed_consume_allocations(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);

        $user->refund($payment, 40_000);
        $user->refund($payment, 60_000);

        $consumed = (int) ConfiguredModels::allocation()::query()
            ->where('wallet_transaction_id', $payment->id)
            ->where('type', WalletAllocationType::Consume->value)
            ->sum('amount');

        $restored = (int) ConfiguredModels::allocation()::query()
            ->where('type', WalletAllocationType::Restore->value)
            ->whereIn(
                'original_allocation_id',
                ConfiguredModels::allocation()::query()
                    ->where('wallet_transaction_id', $payment->id)
                    ->pluck('id')
            )
            ->sum('amount');

        $this->assertSame($consumed, $restored);
        $this->assertLessThanOrEqual($consumed, $restored);
    }
}
