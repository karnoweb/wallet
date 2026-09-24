<?php

namespace Karnoweb\Wallet\Tests\Unit;

use Karnoweb\Wallet\Services\IdempotencyService;
use PHPUnit\Framework\TestCase;

class IdempotencyHashTest extends TestCase
{
    public function test_key_order_does_not_affect_the_hash(): void
    {
        $service = new IdempotencyService();

        $hashA = $service->hashPayload(['amount' => 100, 'club_id' => 1]);
        $hashB = $service->hashPayload(['club_id' => 1, 'amount' => 100]);

        $this->assertSame($hashA, $hashB);
    }

    public function test_a_different_value_produces_a_different_hash(): void
    {
        $service = new IdempotencyService();

        $hashA = $service->hashPayload(['amount' => 100]);
        $hashB = $service->hashPayload(['amount' => 200]);

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_list_arrays_preserve_their_order_semantics(): void
    {
        $service = new IdempotencyService();

        $hashA = $service->hashPayload(['segments' => ['a', 'b']]);
        $hashB = $service->hashPayload(['segments' => ['b', 'a']]);

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_nested_maps_are_also_key_sorted(): void
    {
        $service = new IdempotencyService();

        $hashA = $service->hashPayload(['rules' => ['b' => 2, 'a' => 1]]);
        $hashB = $service->hashPayload(['rules' => ['a' => 1, 'b' => 2]]);

        $this->assertSame($hashA, $hashB);
    }

    public function test_the_hash_is_a_sha256_hex_digest(): void
    {
        $service = new IdempotencyService();

        $hash = $service->hashPayload(['amount' => 1]);

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }
}
