<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Facades\Wallet;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 26 of TEST-SCENARIOS.md: "Facade".
 */
class FacadeTest extends TestCase
{
    /** F001 */
    public function test_facade_balance_matches_trait_balance(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $this->assertSame($user->balance(), Wallet::for($user)->balance());
    }

    /** F002 */
    public function test_facade_charge_creates_the_same_records_as_the_trait(): void
    {
        $user = User::create(['name' => 'Alice']);

        $transaction = Wallet::for($user)->charge(500_000);

        $this->assertSame(500_000, $user->balance());
        $this->assertDatabaseHas('wallet_transactions', ['id' => $transaction->id, 'amount' => 500_000]);
        $this->assertDatabaseCount('wallet_credits', 1);
    }

    /** F003 */
    public function test_using_selected_wallet_operates_on_that_instance(): void
    {
        $user = User::create(['name' => 'Alice']);
        $wallet = $user->wallet();

        Wallet::for($user)->using($wallet)->charge(300_000);

        $this->assertSame(300_000, $wallet->balance());
        $this->assertSame(300_000, $user->balance());
    }

    /** F004 */
    public function test_trait_delegates_to_the_manager_with_no_duplicate_financial_logic(): void
    {
        $user = User::create(['name' => 'Alice']);

        $viaTrait = $user->charge(200_000);
        $viaFacade = Wallet::for($user)->charge(200_000);

        // Both call paths land on the exact same WalletOwnerManager/service
        // chain, so their effects on the wallet are indistinguishable.
        $this->assertSame($viaTrait->wallet_id, $viaFacade->wallet_id);
        $this->assertSame(400_000, $user->balance());
    }
}
