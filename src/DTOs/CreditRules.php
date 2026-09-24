<?php

namespace Karnoweb\Wallet\DTOs;

use Karnoweb\Wallet\Enums\CreditExpireAction;

/**
 * Rules snapshotted onto a WalletCredit at charge/grant time. Changing the
 * source Organization/Contract configuration later never retroactively
 * changes credits that were already created.
 */
final class CreditRules
{
    public function __construct(
        public readonly array $allowedClubIds = [],
        public readonly array $allowedServiceIds = [],
        public readonly ?\DateTimeInterface $startsAt = null,
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly CreditExpireAction $expireAction = CreditExpireAction::None,
        public readonly ?bool $cashWithdrawable = null,
    ) {
    }

    public static function fromArray(array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        $expireAction = $data['expire_action'] ?? CreditExpireAction::None;

        if (is_string($expireAction)) {
            $expireAction = CreditExpireAction::from($expireAction);
        }

        return new self(
            allowedClubIds: array_values(array_map('intval', $data['allowed_club_ids'] ?? [])),
            allowedServiceIds: array_values(array_map('intval', $data['allowed_service_ids'] ?? [])),
            startsAt: isset($data['starts_at']) ? \Illuminate\Support\Carbon::parse($data['starts_at']) : null,
            expiresAt: isset($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at']) : null,
            expireAction: $expireAction,
            cashWithdrawable: array_key_exists('cash_withdrawable', $data) ? (bool) $data['cash_withdrawable'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'allowed_club_ids' => $this->allowedClubIds,
            'allowed_service_ids' => $this->allowedServiceIds,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'expire_action' => $this->expireAction->value,
            'cash_withdrawable' => $this->cashWithdrawable,
        ];
    }
}
