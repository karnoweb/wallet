<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\ThrowingCreditSelectionStrategy;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Proves idempotency is atomic with the financial write: a failed
 * attempt must not leave an orphan WalletOperation that breaks retries.
 */
class IdempotencyRetrySafetyTest extends TestCase
{
    public function test_failed_payment_with_idempotency_key_allows_successful_retry(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(50_000);

        $options = ['idempotency_key' => 'pay:retry:1'];

        try {
            $user->pay(100_000, $options);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
            // expected
        }

        // Charge keeps its own operation; the failed payment must not leave
        // an orphan keyed operation behind.
        $this->assertSame(0, ConfiguredModels::operation()::query()
            ->where('idempotency_key', 'pay:retry:1')
            ->count());
        $this->assertDatabaseCount('wallet_transactions', 1); // charge only
        $this->assertSame(50_000, $user->balance());

        $user->charge(50_000);

        $payment = $user->pay(100_000, $options);

        $this->assertSame(100_000, $payment->amount);
        $this->assertSame(0, $user->balance());
        $this->assertDatabaseCount('wallet_transactions', 3); // 2 charges + 1 payment
    }

    public function test_exception_mid_payment_rolls_back_operation_and_allows_retry(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);

        $options = [
            'idempotency_key' => 'pay:retry:boom',
            'credit_selection_strategy' => ThrowingCreditSelectionStrategy::class,
        ];

        try {
            $user->pay(50_000, $options);
            $this->fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced allocation failure', $e->getMessage());
        }

        $this->assertSame(0, ConfiguredModels::operation()::query()
            ->where('idempotency_key', 'pay:retry:boom')
            ->count());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'charge')->count());
        $this->assertSame(0, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());
        $this->assertSame(100_000, $user->balance());

        unset($options['credit_selection_strategy']);

        $payment = $user->pay(50_000, $options);

        $this->assertSame(50_000, $payment->amount);
        $this->assertSame(50_000, $user->balance());
    }

    public function test_successful_payment_retry_returns_same_transaction(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);

        $options = ['idempotency_key' => 'pay:same:1'];
        $first = $user->pay(40_000, $options);
        $second = $user->pay(40_000, $options);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(60_000, $user->balance());
        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());
    }
}
