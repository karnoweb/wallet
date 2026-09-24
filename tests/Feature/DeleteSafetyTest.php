<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 24 of TEST-SCENARIOS.md: "Delete safety".
 */
class DeleteSafetyTest extends TestCase
{
    /** DS001 */
    public function test_deleting_the_owner_does_not_delete_transaction_history(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $walletId = $user->wallet()->id;

        $user->delete();

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $walletId]);
    }

    /** DS002 */
    public function test_soft_deleting_the_wallet_does_not_cascade_to_financial_history(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $wallet = $user->wallet();
        $walletId = $wallet->id;

        $wallet->delete(); // SoftDeletes: row remains, deleted_at set

        $this->assertSoftDeleted('wallets', ['id' => $walletId]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $walletId]);
        $this->assertDatabaseCount('wallet_credits', 1);
    }

    /** DS003 */
    public function test_deleting_the_causer_does_not_delete_transactions(): void
    {
        $causer = User::create(['name' => 'Causer']);
        $owner = User::create(['name' => 'Owner']);

        $owner->charge(500_000, ['causer_id' => $causer->id]);
        $causer->delete();

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['causer_id' => $causer->id]);
    }
}
