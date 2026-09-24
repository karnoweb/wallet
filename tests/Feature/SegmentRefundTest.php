<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InvalidRefund;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class SegmentRefundTest extends TestCase
{
    /** SR001 */
    public function test_refund_can_target_a_single_segment(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $payment = $user->pay(500_000, [
            'segments' => [
                ['key' => 'a', 'amount' => 200_000],
                ['key' => 'b', 'amount' => 300_000],
            ],
        ]);

        $user->refund($payment, 200_000, ['segment_keys' => ['a']]);

        // Only segment "a"'s 200_000 came back.
        $this->assertSame(200_000, $user->wallet()->credits()->first()->remaining_amount);
    }

    /** SR002 */
    public function test_segment_refund_cannot_exceed_that_segments_refundable_value(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $payment = $user->pay(500_000, [
            'segments' => [
                ['key' => 'a', 'amount' => 200_000],
                ['key' => 'b', 'amount' => 300_000],
            ],
        ]);

        $this->expectException(InvalidRefund::class);
        $user->refund($payment, 250_000, ['segment_keys' => ['a']]);
    }
}
