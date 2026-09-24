<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Services\ExpirationService;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class ExpirationBurnTest extends TestCase
{
    /** EB001 */
    public function test_expiration_command_burns_remaining_credit(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['rules' => [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'burn',
        ]]);

        $this->artisan('wallet:expire-credits')->assertExitCode(0);

        $credit = $user->wallet()->credits()->first();
        $this->assertSame(0, $credit->remaining_amount);
        $this->assertSame(0, $user->balance());
    }

    /** EB002 */
    public function test_running_the_expiration_command_twice_is_a_no_op_the_second_time(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['rules' => [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'burn',
        ]]);

        $service = $this->app->make(ExpirationService::class);

        $first = $service->expireDueCredits(500);
        $this->assertSame(1, $first['processed']);

        $second = $service->expireDueCredits(500);
        $this->assertSame(0, $second['processed']);
        $this->assertSame(0, $second['skipped']);
        $this->assertEmpty($second['failed']);

        $this->assertSame(0, $user->balance());
        $this->assertDatabaseCount('wallet_transactions', 2); // charge + burn, never duplicated
    }
}
