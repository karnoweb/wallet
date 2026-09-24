<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\ImmutableWalletAllocation;
use Karnoweb\Wallet\Exceptions\ImmutableWalletTransaction;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 23 of TEST-SCENARIOS.md: "Immutability".
 */
class ImmutabilityTest extends TestCase
{
    /** IM001 */
    public function test_updating_a_wallet_transaction_is_forbidden(): void
    {
        $user = User::create(['name' => 'Alice']);
        $transaction = $user->charge(500_000);

        $this->expectException(ImmutableWalletTransaction::class);
        $transaction->amount = 999_999;
        $transaction->save();
    }

    /** IM002 */
    public function test_deleting_a_wallet_transaction_is_forbidden(): void
    {
        $user = User::create(['name' => 'Alice']);
        $transaction = $user->charge(500_000);

        $this->expectException(ImmutableWalletTransaction::class);
        $transaction->delete();
    }

    /** IM003 */
    public function test_updating_a_wallet_allocation_is_forbidden(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);
        $allocation = $payment->allocations()->first();

        $this->expectException(ImmutableWalletAllocation::class);
        $allocation->amount = 1;
        $allocation->save();
    }

    /** IM004 */
    public function test_deleting_a_wallet_allocation_is_forbidden(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);
        $allocation = $payment->allocations()->first();

        $this->expectException(ImmutableWalletAllocation::class);
        $allocation->delete();
    }

    /** IM005 */
    public function test_credit_snapshot_fields_cannot_be_changed_directly(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $credit = $user->wallet()->credits()->first();

        $this->expectException(WalletException::class);
        $credit->original_amount = 999_999;
        $credit->save();
    }
}
