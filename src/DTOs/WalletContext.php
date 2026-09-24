<?php

namespace Karnoweb\Wallet\DTOs;

/**
 * Immutable context describing a wallet operation request. Combines every
 * per-operation option a caller may pass (club, causer, references,
 * idempotency, spend segments, ...).
 */
final class WalletContext
{
    public function __construct(
        public readonly ?int $clubId = null,
        public readonly ?int $causerId = null,
        public readonly ?string $transactionId = null,
        public readonly mixed $transactionable = null,
        public readonly ?string $description = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?\DateTimeInterface $occurredAt = null,
        public readonly array $scopes = [],
        public readonly array $segments = [],
        public readonly array $metadata = [],
        /** Raw, un-normalized options as originally supplied by the caller. */
        public readonly array $raw = [],
    ) {
    }

    public static function fromArray(array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        return new self(
            clubId: isset($data['club_id']) ? (int) $data['club_id'] : null,
            causerId: isset($data['causer_id']) ? (int) $data['causer_id'] : null,
            transactionId: $data['transaction_id'] ?? null,
            transactionable: $data['transactionable'] ?? null,
            description: $data['description'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
            occurredAt: isset($data['occurred_at']) ? \Illuminate\Support\Carbon::parse($data['occurred_at']) : null,
            scopes: $data['scopes'] ?? [],
            segments: $data['segments'] ?? [],
            metadata: $data['metadata'] ?? [],
            raw: $data,
        );
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'club_id' => $this->clubId,
            'causer_id' => $this->causerId,
            'transaction_id' => $this->transactionId,
            'description' => $this->description,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
            'scopes' => $this->scopes,
            'segments' => $this->segments,
            'metadata' => $this->metadata,
        ];
    }
}
