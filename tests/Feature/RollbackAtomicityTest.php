<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\ThrowingCreditSelectionStrategy;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Multi-step financial flows must leave no partial writes on exception.
 */
class RollbackAtomicityTest extends TestCase
{
    public function test_payment_exception_rolls_back_allocations_and_remaining(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $credit = ConfiguredModels::credit()::query()->first();

        try {
            $user->pay(40_000, [
                'credit_selection_strategy' => ThrowingCreditSelectionStrategy::class,
            ]);
            $this->fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(100_000, $credit->fresh()->remaining_amount);
        $this->assertSame(0, ConfiguredModels::allocation()::query()->count());
        $this->assertSame(0, ConfiguredModels::transaction()::query()->where('type', 'payment')->count());
        $this->assertSame(100_000, $user->balance());
    }

    public function test_transfer_exception_rolls_back_source_and_destination(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);
        $source->charge(100_000);

        try {
            $source->transferTo($destination, 40_000, [
                'credit_selection_strategy' => ThrowingCreditSelectionStrategy::class,
            ]);
            $this->fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(100_000, $source->balance());
        $this->assertSame(0, $destination->balance());
        $this->assertSame(0, ConfiguredModels::transaction()::query()->where('type', 'transfer')->count());
        $this->assertSame(0, ConfiguredModels::allocation()::query()->count());
    }

    public function test_grant_exception_rolls_back_source_and_destination(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);
        $source->charge(100_000);

        try {
            $source->grantTo($destination, 40_000, ['allowed_club_ids' => [1]], [
                'credit_selection_strategy' => ThrowingCreditSelectionStrategy::class,
            ]);
            $this->fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(100_000, $source->balance());
        $this->assertSame(0, $destination->balance());
        $this->assertSame(0, ConfiguredModels::allocation()::query()->count());
    }

    public function test_successful_transfer_preserves_equal_source_debit_and_destination_credit(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);
        $source->charge(100_000);

        $result = $source->transferTo($destination, 40_000);

        $this->assertSame(40_000, $result->sourceTransaction->amount);
        $this->assertSame(40_000, $result->destinationTransaction->amount);
        $this->assertSame(60_000, $source->balance());
        $this->assertSame(40_000, $destination->balance());

        $consumed = (int) ConfiguredModels::allocation()::query()
            ->where('type', WalletAllocationType::Consume->value)
            ->sum('amount');

        $this->assertSame(40_000, $consumed);
    }
}
