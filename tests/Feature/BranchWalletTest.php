<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class BranchWalletTest extends TestCase
{
    /** BR001 */
    public function test_charge_in_one_club_and_pay_in_another_uses_one_global_wallet(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, ['club_id' => 1]);
        $payment = $user->pay(300_000, ['club_id' => 2]);

        $this->assertDatabaseCount('wallets', 1);
        $this->assertSame(2, $payment->club_id);

        $allocation = $payment->allocations()->first();
        $this->assertSame(1, $allocation->credit->sourceTransaction->club_id);
        $this->assertSame(700_000, $user->balance());
    }

    /** BR002 */
    public function test_payment_allocates_across_credits_from_multiple_clubs(): void
    {
        $user = User::create(['name' => 'Alice']);

        $creditA = $user->charge(500_000, ['club_id' => 1]);
        $creditB = $user->charge(300_000, ['club_id' => 2]);

        $payment = $user->pay(600_000, ['club_id' => 3]);

        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame(500_000, $allocations[0]->amount);
        $this->assertSame(100_000, $allocations[1]->amount);

        $wallet = $user->wallet();
        $creditRowA = $wallet->credits()->where('source_transaction_id', $creditA->id)->first();
        $creditRowB = $wallet->credits()->where('source_transaction_id', $creditB->id)->first();

        $this->assertSame(0, $creditRowA->remaining_amount);
        $this->assertSame(200_000, $creditRowB->remaining_amount);
    }

    /** BR003 */
    public function test_operating_across_three_clubs_never_creates_more_than_one_wallet(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(100_000, ['club_id' => 1]);
        $user->charge(100_000, ['club_id' => 2]);
        $user->charge(100_000, ['club_id' => 3]);

        $this->assertDatabaseCount('wallets', 1);
        $this->assertSame(300_000, $user->balance());
    }
}
