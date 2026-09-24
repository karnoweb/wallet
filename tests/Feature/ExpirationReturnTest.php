<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Services\ExpirationService;
use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class ExpirationReturnTest extends TestCase
{
    /** ER001 */
    public function test_expiring_an_unused_grant_returns_it_to_the_source_wallet(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);

        $organization->charge(1_000_000);
        $organization->grantTo($employee, 400_000, [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'return',
        ]);

        $this->assertSame(600_000, $organization->balance());
        $this->assertSame(400_000, $employee->balance());

        $this->artisan('wallet:expire-credits')->assertExitCode(0);

        $this->assertSame(0, $employee->balance());
        $this->assertSame(1_000_000, $organization->balance());
    }

    /** ER002 */
    public function test_return_restores_the_exact_original_source_allocation(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);

        $organization->charge(1_000_000);
        $organization->grantTo($employee, 400_000, [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'return',
        ]);

        $this->artisan('wallet:expire-credits')->assertExitCode(0);

        $sourceCredit = $organization->wallet()->credits()->first();
        $this->assertSame(1_000_000, $sourceCredit->remaining_amount);
        $this->assertSame(1_000_000, $sourceCredit->original_amount);
    }

    /** ER003 */
    public function test_a_return_credit_with_unresolvable_lineage_is_not_silently_burned(): void
    {
        $user = User::create(['name' => 'Alice']);

        // A plain charge has no parent_credit_id / source_wallet_id, so
        // "return" cannot be resolved for it.
        $user->charge(500_000, ['rules' => [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'return',
        ]]);

        $service = $this->app->make(ExpirationService::class);
        $summary = $service->expireDueCredits(500);

        $this->assertSame(0, $summary['processed']);
        $this->assertCount(1, $summary['failed']);

        // The value must still be there: never silently burned.
        $this->assertSame(500_000, $user->balance());
        $credit = $user->wallet()->credits()->first();
        $this->assertSame(500_000, $credit->remaining_amount);
    }
}
