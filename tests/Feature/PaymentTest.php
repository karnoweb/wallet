<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class PaymentTest extends TestCase
{
    /** P001 */
    public function test_full_payment_from_one_credit(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(1_000_000);

        $payment = $user->pay(400_000);

        $this->assertSame(600_000, $user->balance());
        $this->assertSame(400_000, $payment->amount);
        $this->assertSame(-1, $payment->sign);

        $credit = $payment->wallet->credits()->first();
        $this->assertSame(600_000, $credit->remaining_amount);

        $allocations = $payment->allocations;
        $this->assertCount(1, $allocations);
        $this->assertSame(400_000, $allocations->first()->amount);
        $this->assertSame('consume', $allocations->first()->type->value);
    }

    /** P002 */
    public function test_fifo_spends_across_two_credits_in_order(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000); // Credit A, first
        $user->charge(500_000); // Credit B, second

        $payment = $user->pay(700_000);

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(0, $credits[0]->remaining_amount);
        $this->assertSame(300_000, $credits[1]->remaining_amount);

        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame(500_000, $allocations[0]->amount);
        $this->assertSame(200_000, $allocations[1]->amount);
    }

    /** P003 */
    public function test_fifo_tie_break_by_id_when_created_at_matches(): void
    {
        $user = User::create(['name' => 'Alice']);

        $a = $user->charge(300_000);
        $b = $user->charge(300_000);

        // Force identical created_at to prove the id tie-break.
        $creditClass = \Karnoweb\Wallet\Support\ConfiguredModels::credit();
        $creditClass::query()->update(['created_at' => now()]);

        $payment = $user->pay(400_000);

        $allocations = $payment->allocations()->orderBy('id')->get();
        $creditA = $creditClass::query()->where('source_transaction_id', $a->id)->first();
        $creditB = $creditClass::query()->where('source_transaction_id', $b->id)->first();

        $this->assertSame($creditA->id, $allocations[0]->wallet_credit_id);
        $this->assertSame(300_000, $allocations[0]->amount);
        $this->assertSame($creditB->id, $allocations[1]->wallet_credit_id);
        $this->assertSame(100_000, $allocations[1]->amount);
    }

    /** P004 */
    public function test_insufficient_balance_throws_and_changes_nothing(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        try {
            $user->pay(600_000);
            $this->fail('Expected InsufficientBalance to be thrown.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        $this->assertDatabaseCount('wallet_transactions', 1); // only the charge
        $this->assertDatabaseCount('wallet_allocations', 0);
        $this->assertSame(500_000, $user->wallet()->credits()->first()->remaining_amount);
    }

    /** P005 */
    public function test_payment_rolls_back_atomically_when_a_later_segment_fails(): void
    {
        $user = User::create(['name' => 'Alice']);

        // Unrestricted credit large enough to fully cover segment A.
        $user->charge(300_000);

        try {
            $user->pay(400_000, [
                'segments' => [
                    // Segment "a" fully drains the only available credit.
                    ['key' => 'a', 'amount' => 300_000],
                    // Segment "b" must then fail with InsufficientBalance,
                    // since segment "a" has already been planned and
                    // consumed inside the same DB transaction.
                    ['key' => 'b', 'amount' => 100_000],
                ],
            ]);
            $this->fail('Expected InsufficientBalance to be thrown.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        // Everything from segment "a" must have been rolled back too.
        $this->assertDatabaseCount('wallet_transactions', 1); // only the charge
        $this->assertDatabaseCount('wallet_allocations', 0);
        $this->assertSame(300_000, $user->wallet()->credits()->first()->remaining_amount);
        $this->assertSame(300_000, $user->balance());
    }
}
