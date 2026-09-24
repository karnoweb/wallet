<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 30 of TEST-SCENARIOS.md: "Reconciliation".
 */
class ReconciliationCommandTest extends TestCase
{
    /** RC001 */
    public function test_a_healthy_wallet_passes_reconciliation(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $user->pay(200_000);

        $this->artisan('wallet:reconcile')->assertExitCode(0);
    }

    /** RC002 */
    public function test_a_tampered_remaining_amount_is_detected(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $credit = $user->wallet()->credits()->first();

        // Direct DB tamper, bypassing the model's immutability guard.
        DB::table('wallet_credits')->where('id', $credit->id)->update(['remaining_amount' => 100_000]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('wallet:reconcile', ['--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(1, $exitCode);
        $decoded = json_decode($output, true);
        $this->assertNotEmpty($decoded['mismatches']);
        $this->assertSame('credit_remaining_amount', $decoded['mismatches'][0]['check']);
        $this->assertSame(500_000, $decoded['mismatches'][0]['expected_remaining_amount']);
        $this->assertSame(100_000, $decoded['mismatches'][0]['actual_remaining_amount']);
    }

    /** RC003 */
    public function test_reconciliation_never_auto_fixes_the_tampered_value(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $credit = $user->wallet()->credits()->first();

        DB::table('wallet_credits')->where('id', $credit->id)->update(['remaining_amount' => 100_000]);

        $this->artisan('wallet:reconcile')->assertExitCode(1);

        $this->assertSame(100_000, DB::table('wallet_credits')->where('id', $credit->id)->value('remaining_amount'));
    }
}
