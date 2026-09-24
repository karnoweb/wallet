<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InvalidSpendSegments;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 11 of TEST-SCENARIOS.md: "Mixed order segments".
 */
class SegmentTest extends TestCase
{
    /** SG001 */
    public function test_segment_sum_mismatch_throws_and_changes_nothing(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(1_000_000);

        try {
            $user->pay(700_000, [
                'segments' => [
                    ['key' => 'a', 'amount' => 300_000],
                    ['key' => 'b', 'amount' => 300_000],
                ],
            ]);
            $this->fail('Expected InvalidSpendSegments to be thrown.');
        } catch (InvalidSpendSegments $e) {
            // expected
        }

        $this->assertDatabaseCount('wallet_transactions', 1); // only the charge
        $this->assertSame(1_000_000, $user->balance());
    }

    /** SG002 */
    public function test_duplicate_segment_keys_are_rejected(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(1_000_000);

        $this->expectException(InvalidSpendSegments::class);

        $user->pay(600_000, [
            'segments' => [
                ['key' => 'same', 'amount' => 300_000],
                ['key' => 'same', 'amount' => 300_000],
            ],
        ]);
    }

    /** SG003 */
    public function test_restricted_credit_only_funds_its_eligible_segment(): void
    {
        $user = User::create(['name' => 'Alice']);

        // Created first so FIFO prefers it for the service-10 segment.
        $user->charge(300_000, ['rules' => ['allowed_service_ids' => [10]]]);
        $user->charge(500_000);

        $payment = $user->pay(700_000, [
            'segments' => [
                ['key' => 'order-item:A', 'amount' => 300_000, 'scopes' => ['service' => [10]]],
                ['key' => 'order-item:B', 'amount' => 400_000, 'scopes' => ['service' => [20]]],
            ],
        ]);

        $allocations = $payment->allocations()->orderBy('id')->get();

        $this->assertSame('order-item:A', $allocations[0]->segment_key);
        $this->assertSame(300_000, $allocations[0]->amount);

        $this->assertSame('order-item:B', $allocations[1]->segment_key);
        $this->assertSame(400_000, $allocations[1]->amount);

        // Segment A drained the restricted credit entirely.
        $creditA = $user->wallet()->credits()->orderBy('id')->first();
        $this->assertSame(0, $creditA->remaining_amount);
    }

    /** SG004 */
    public function test_fifo_is_still_respected_within_one_eligible_segment(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(200_000);
        $user->charge(200_000);

        $payment = $user->pay(300_000, [
            'segments' => [['key' => 'only', 'amount' => 300_000]],
        ]);

        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame(200_000, $allocations[0]->amount);
        $this->assertSame(100_000, $allocations[1]->amount);

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(0, $credits[0]->remaining_amount);
        $this->assertSame(100_000, $credits[1]->remaining_amount);
    }
}
