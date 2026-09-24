<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Karnoweb\Wallet\Contracts\CauserResolver;
use Karnoweb\Wallet\Contracts\ClubResolver;
use Karnoweb\Wallet\DTOs\WalletContext;
use Karnoweb\Wallet\Exceptions\ClubRequired;

/**
 * Resolves the effective {@see WalletContext} (club id, causer id, ...)
 * and effective settings for a single operation, applying the
 * club/causer resolvers and the settings precedence exactly once so
 * every flow service (Charge, Payment, Transfer, ...) behaves
 * consistently.
 */
class OperationContextService
{
    public function __construct(
        protected ClubResolver $clubResolver,
        protected CauserResolver $causerResolver,
        protected WalletSettingsService $settings,
    ) {
    }

    /**
     * @return array{context: WalletContext, settings: array}
     */
    public function build(?Model $owner, array|WalletContext $options): array
    {
        $raw = $options instanceof WalletContext ? $options->raw : $options;

        $settings = $this->settings->resolve($owner, $raw);

        $clubId = $raw['club_id'] ?? $this->clubResolver->resolve($owner, $raw);
        $clubId = $clubId !== null ? (int) $clubId : null;

        if ($clubId === null && ! empty($settings['club_required'])) {
            throw ClubRequired::make();
        }

        $causerId = $raw['causer_id'] ?? $this->causerResolver->resolve($owner, $raw);
        $causerId = $causerId !== null ? (int) $causerId : null;

        $context = new WalletContext(
            clubId: $clubId,
            causerId: $causerId,
            transactionId: $raw['transaction_id'] ?? null,
            transactionable: $raw['transactionable'] ?? null,
            description: $raw['description'] ?? null,
            idempotencyKey: $raw['idempotency_key'] ?? null,
            occurredAt: isset($raw['occurred_at']) ? Carbon::parse($raw['occurred_at']) : Carbon::now(),
            scopes: $raw['scopes'] ?? [],
            segments: $raw['segments'] ?? [],
            metadata: $raw['metadata'] ?? [],
            raw: $raw,
        );

        return ['context' => $context, 'settings' => $settings];
    }
}
