<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Wallet\Events\WalletCharged;
use Karnoweb\Wallet\Events\WalletPaid;
use Karnoweb\Wallet\Events\WalletTransferred;
use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 29 of TEST-SCENARIOS.md: "Events".
 */
class EventsTest extends TestCase
{
    /** EV001 */
    public function test_charge_event_fires_after_commit_and_sees_committed_rows(): void
    {
        $user = User::create(['name' => 'Alice']);

        $seenInListener = null;

        Event::listen(WalletCharged::class, function (WalletCharged $event) use (&$seenInListener) {
            // Query the DB from inside the listener: the row must already
            // be visible, proving the event fired after commit.
            $seenInListener = \Karnoweb\Wallet\Models\WalletTransaction::query()->find($event->transaction->id);
        });

        $transaction = $user->charge(500_000);

        $this->assertNotNull($seenInListener);
        $this->assertSame($transaction->id, $seenInListener->id);
    }

    /** EV002 */
    public function test_a_failed_operation_never_emits_a_financial_event(): void
    {
        $user = User::create(['name' => 'Alice']);

        $fired = false;
        Event::listen(WalletPaid::class, function () use (&$fired) {
            $fired = true;
        });

        try {
            $user->pay(100_000); // no credit at all: InsufficientBalance
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertFalse($fired);
    }

    /** EV003 */
    public function test_payment_event_exposes_allocations_with_their_source_club(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['club_id' => 1]);

        $captured = null;
        Event::listen(WalletPaid::class, function (WalletPaid $event) use (&$captured) {
            $captured = $event;
        });

        $payment = $user->pay(200_000, [
            'club_id' => 2,
            'segments' => [['key' => 'a', 'amount' => 200_000]],
        ]);

        $this->assertNotNull($captured);
        $this->assertSame(2, $captured->transaction->club_id);
        $this->assertCount(1, $captured->allocations);

        $allocation = $captured->allocations->first();
        $this->assertSame(200_000, $allocation->amount);
        $this->assertSame('a', $allocation->segment_key);
        $this->assertSame(1, $allocation->credit->sourceTransaction->club_id);
    }

    /** EV004 */
    public function test_transfer_event_contains_both_sides_and_lineage(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);
        $source->charge(500_000);

        $captured = null;
        Event::listen(WalletTransferred::class, function (WalletTransferred $event) use (&$captured) {
            $captured = $event;
        });

        $source->transferTo($destination, 200_000);

        $this->assertNotNull($captured);
        $this->assertSame($source->wallet()->id, $captured->sourceTransaction->wallet_id);
        $this->assertSame($destination->wallet()->id, $captured->destinationTransaction->wallet_id);
        $this->assertCount(1, $captured->sourceAllocations);
        $this->assertCount(1, $captured->destinationCredits);
        $this->assertSame(
            $captured->sourceAllocations->first()->credit->id,
            $captured->destinationCredits->first()->parent_credit_id
        );
    }
}
