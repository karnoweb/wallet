<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Exceptions\InvalidRefund;
use Karnoweb\Wallet\Support\ConfiguredModels;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Refund correctness and sequential double-refund prevention.
 * Real concurrent races live in tests/Concurrency.
 */
class RefundConcurrencySafetyTest extends TestCase
{
    public function test_second_full_refund_of_same_payment_fails(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);

        $first = $user->refund($payment);
        $this->assertSame(100_000, $first->amount);
        $this->assertSame(100_000, $user->balance());

        try {
            $user->refund($payment);
            $this->fail('Expected InvalidRefund.');
        } catch (InvalidRefund $e) {
            $this->assertStringContainsString('refundable', strtolower($e->getMessage()));
        }

        $this->assertSame(1, ConfiguredModels::transaction()::query()->where('type', 'refund')->count());
        $this->assertSame(100_000, $user->balance());
    }

    public function test_sequential_partial_refunds_cannot_exceed_payment(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);

        $user->refund($payment, 70_000);
        $this->assertSame(70_000, $user->balance());

        try {
            $user->refund($payment, 70_000);
            $this->fail('Expected InvalidRefund when exceeding refundable amount.');
        } catch (InvalidRefund $e) {
            // expected
        }

        $user->refund($payment, 30_000);

        $this->assertSame(100_000, $user->balance());

        $restoreTotal = (int) ConfiguredModels::allocation()::query()
            ->where('type', WalletAllocationType::Restore->value)
            ->sum('amount');

        $this->assertSame(100_000, $restoreTotal);
    }

    public function test_failed_refund_with_idempotency_key_allows_retry_after_funds_available(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $payment = $user->pay(100_000);
        $user->refund($payment, 100_000);

        // Nothing left to refund; attempt with a key must not orphan an operation.
        try {
            $user->refund($payment, 50_000, ['idempotency_key' => 'refund:retry:1']);
            $this->fail('Expected InvalidRefund.');
        } catch (InvalidRefund $e) {
            // expected
        }

        $ops = ConfiguredModels::operation()::query()->where('idempotency_key', 'refund:retry:1')->count();
        $this->assertSame(0, $ops);
    }
}
