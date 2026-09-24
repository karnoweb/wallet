<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\IdempotencyConflict;
use Karnoweb\Wallet\Tests\Support\Order;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class ChargeTest extends TestCase
{
    /** C001 */
    public function test_basic_charge_creates_transaction_and_credit(): void
    {
        $user = User::create(['name' => 'Alice']);

        $transaction = $user->charge(1_000_000);

        $this->assertSame(1_000_000, $user->balance());
        $this->assertSame('charge', $transaction->type->value);
        $this->assertSame(1, $transaction->sign);
        $this->assertSame(1_000_000, $transaction->amount);

        $credit = $transaction->wallet->credits()->first();
        $this->assertSame(1_000_000, $credit->original_amount);
        $this->assertSame(1_000_000, $credit->remaining_amount);
        $this->assertSame($transaction->id, $credit->source_transaction_id);
    }

    /** C002 */
    public function test_charge_with_club_sets_transaction_and_source_club(): void
    {
        $user = User::create(['name' => 'Alice']);

        $transaction = $user->charge(1_000_000, ['club_id' => 1]);

        $this->assertSame(1, $transaction->club_id);

        $credit = $transaction->wallet->credits()->first();
        $this->assertSame($transaction->id, $credit->source_transaction_id);
        $this->assertSame(1, $credit->sourceTransaction->club_id);
    }

    /** C003 */
    public function test_charge_with_transactionable_stores_morph_reference(): void
    {
        $user = User::create(['name' => 'Alice']);
        $order = Order::create(['name' => 'order-1']);

        $transaction = $user->charge(500_000, ['transactionable' => $order]);

        $this->assertSame(Order::class, $transaction->transactionable_type);
        $this->assertSame($order->id, $transaction->transactionable_id);
    }

    /** C004 */
    public function test_charge_idempotent_duplicate_returns_prior_result(): void
    {
        $user = User::create(['name' => 'Alice']);

        $options = ['club_id' => 1, 'idempotency_key' => 'charge:1'];

        $first = $user->charge(1_000_000, $options);
        $second = $user->charge(1_000_000, $options);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('wallet_credits', 1);
        $this->assertSame(1_000_000, $user->balance());
    }

    /** C005 */
    public function test_charge_idempotency_conflict_on_different_payload(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000, ['idempotency_key' => 'charge:2']);

        $this->expectException(IdempotencyConflict::class);
        $user->charge(2_000_000, ['idempotency_key' => 'charge:2']);
    }
}
