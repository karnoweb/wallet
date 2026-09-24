<?php

namespace Karnoweb\Wallet\DTOs;

use Karnoweb\Wallet\Exceptions\InvalidSpendSegments;

/**
 * One independently eligible/refundable slice of a payment/deduct amount.
 *
 * Example (mixed-service order):
 *
 * ```php
 * [
 *     'key' => 'order-item:1001',
 *     'amount' => 300_000,
 *     'scopes' => [
 *         'service' => [10],
 *     ],
 * ]
 * ```
 */
final class SpendSegment
{
    public function __construct(
        public readonly ?string $key,
        public readonly int $amount,
        public readonly array $scopes = [],
    ) {
        if ($this->amount <= 0) {
            throw new InvalidSpendSegments('Spend segment amount must be greater than zero.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'] ?? null,
            amount: (int) ($data['amount'] ?? 0),
            scopes: $data['scopes'] ?? [],
        );
    }

    /**
     * Build the list of segments for a payment/deduct amount.
     *
     * When no segments are supplied, one implicit segment covering the
     * full amount is created. When segments are supplied, their sum MUST
     * equal `$amount` and every segment key MUST be unique.
     *
     * @return array<int, self>
     */
    public static function buildFor(int $amount, array $segments, array $fallbackScopes = []): array
    {
        if (empty($segments)) {
            return [new self(key: null, amount: $amount, scopes: $fallbackScopes)];
        }

        $built = array_map(fn (array $segment) => self::fromArray($segment), $segments);

        $sum = array_sum(array_map(fn (self $segment) => $segment->amount, $built));

        if ($sum !== $amount) {
            throw new InvalidSpendSegments(
                "Sum of spend segments ({$sum}) must equal the requested amount ({$amount})."
            );
        }

        $keys = array_filter(array_map(fn (self $segment) => $segment->key, $built));

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidSpendSegments('Spend segment keys must be unique within one operation.');
        }

        return $built;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'amount' => $this->amount,
            'scopes' => $this->scopes,
        ];
    }
}
