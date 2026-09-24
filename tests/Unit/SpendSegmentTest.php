<?php

namespace Karnoweb\Wallet\Tests\Unit;

use Karnoweb\Wallet\DTOs\SpendSegment;
use Karnoweb\Wallet\Exceptions\InvalidSpendSegments;
use PHPUnit\Framework\TestCase;

class SpendSegmentTest extends TestCase
{
    public function test_a_segment_with_a_non_positive_amount_is_rejected(): void
    {
        $this->expectException(InvalidSpendSegments::class);
        new SpendSegment(key: 'a', amount: 0);
    }

    public function test_no_segments_builds_one_implicit_segment_covering_the_full_amount(): void
    {
        $segments = SpendSegment::buildFor(500_000, [], ['service' => [10]]);

        $this->assertCount(1, $segments);
        $this->assertSame(500_000, $segments[0]->amount);
        $this->assertNull($segments[0]->key);
        $this->assertSame(['service' => [10]], $segments[0]->scopes);
    }

    public function test_segment_sum_must_equal_the_requested_amount(): void
    {
        $this->expectException(InvalidSpendSegments::class);

        SpendSegment::buildFor(500_000, [
            ['key' => 'a', 'amount' => 100_000],
            ['key' => 'b', 'amount' => 100_000],
        ]);
    }

    public function test_segment_keys_must_be_unique(): void
    {
        $this->expectException(InvalidSpendSegments::class);

        SpendSegment::buildFor(200_000, [
            ['key' => 'x', 'amount' => 100_000],
            ['key' => 'x', 'amount' => 100_000],
        ]);
    }

    public function test_valid_segments_are_built_in_order(): void
    {
        $segments = SpendSegment::buildFor(300_000, [
            ['key' => 'a', 'amount' => 100_000],
            ['key' => 'b', 'amount' => 200_000, 'scopes' => ['service' => [5]]],
        ]);

        $this->assertCount(2, $segments);
        $this->assertSame('a', $segments[0]->key);
        $this->assertSame(100_000, $segments[0]->amount);
        $this->assertSame('b', $segments[1]->key);
        $this->assertSame(200_000, $segments[1]->amount);
        $this->assertSame(['service' => [5]], $segments[1]->scopes);
    }
}
