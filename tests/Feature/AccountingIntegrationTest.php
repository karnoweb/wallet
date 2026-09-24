<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Wallet\Events\WalletPaid;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 32 of TEST-SCENARIOS.md: "Accounting integration scenarios".
 *
 * The package does not implement or test accounting journal rules; it
 * only proves the WalletPaid event exposes enough data for a host
 * Accounting module to derive cross-branch settlement on its own.
 */
class AccountingIntegrationTest extends TestCase
{
    /** A001 */
    public function test_same_club_payment_exposes_matching_source_and_operation_club(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['club_id' => 1]);

        $captured = null;
        Event::listen(WalletPaid::class, function (WalletPaid $event) use (&$captured) {
            $captured = $event;
        });

        $user->pay(200_000, ['club_id' => 1]);

        $this->assertSame(1, $captured->transaction->club_id);
        $this->assertSame(1, $captured->allocations->first()->credit->sourceTransaction->club_id);
    }

    /** A002 */
    public function test_cross_club_payment_exposes_both_operation_and_source_club(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['club_id' => 1]);

        $captured = null;
        Event::listen(WalletPaid::class, function (WalletPaid $event) use (&$captured) {
            $captured = $event;
        });

        $payment = $user->pay(200_000, ['club_id' => 2]);

        $this->assertSame(2, $captured->transaction->club_id); // operation club
        $this->assertSame(1, $captured->allocations->first()->credit->sourceTransaction->club_id); // source club
        $this->assertSame(200_000, $captured->allocations->first()->amount);
        $this->assertSame(200_000, $payment->amount);
    }

    /** A003 */
    public function test_multi_source_cross_club_payment_exposes_each_sources_club_and_amount(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['club_id' => 1]);
        $user->charge(300_000, ['club_id' => 2]);

        $captured = null;
        Event::listen(WalletPaid::class, function (WalletPaid $event) use (&$captured) {
            $captured = $event;
        });

        $user->pay(600_000, ['club_id' => 3]);

        $this->assertSame(3, $captured->transaction->club_id);

        $bySourceClub = $captured->allocations
            ->groupBy(fn ($allocation) => $allocation->credit->sourceTransaction->club_id)
            ->map(fn ($group) => $group->sum('amount'));

        $this->assertSame(500_000, $bySourceClub[1]);
        $this->assertSame(100_000, $bySourceClub[2]);
    }
}
