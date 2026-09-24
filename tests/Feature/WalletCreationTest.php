<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class WalletCreationTest extends TestCase
{
    /** W001 */
    public function test_wallet_is_created_when_owner_is_created(): void
    {
        $user = User::create(['name' => 'Alice']);

        $this->assertDatabaseCount('wallets', 1);

        $wallet = $user->wallet(false);

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertSame(User::class, $wallet->reference_type);
        $this->assertSame($user->id, $wallet->reference_id);
        $this->assertNull($wallet->club_id);
    }

    /** W002 */
    public function test_wallet_method_returns_the_existing_owner_wallet(): void
    {
        $user = User::create(['name' => 'Alice']);

        $wallet = $user->wallet();

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertDatabaseCount('wallets', 1);
    }

    /** W003 */
    public function test_auto_create_disabled_skips_owner_created_hook(): void
    {
        config()->set('wallet.defaults.auto_create', false);

        $user = User::create(['name' => 'Alice']);

        $this->assertDatabaseCount('wallets', 0);
        $this->assertNull($user->wallet(false));
    }

    /** W004 (best-effort without a real multi-connection DB; see Concurrency tests) */
    public function test_repeated_wallet_resolution_never_creates_a_second_wallet(): void
    {
        $user = User::create(['name' => 'Alice']);

        $first = $user->wallet();
        $second = $user->wallet();

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function test_lazy_create_still_works_when_wallet_was_missing(): void
    {
        config()->set('wallet.defaults.auto_create', false);

        $user = User::create(['name' => 'Alice']);
        $this->assertDatabaseCount('wallets', 0);

        config()->set('wallet.defaults.auto_create', true);

        $wallet = $user->wallet(true);

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertDatabaseCount('wallets', 1);
    }
}
