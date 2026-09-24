<?php

namespace Karnoweb\Wallet\DTOs;

use Illuminate\Contracts\Support\Arrayable;

final class WalletSummary implements Arrayable
{
    public function __construct(
        public readonly int $balance,
        public readonly ?int $spendableBalance,
        public readonly int $withdrawableBalance,
        public readonly int $charges,
        public readonly int $payments,
        public readonly int $deducts,
        public readonly int $refunds,
        public readonly int $transfersIn,
        public readonly int $transfersOut,
        public readonly ?\DateTimeInterface $from,
        public readonly ?\DateTimeInterface $to,
    ) {
    }

    public function toArray(): array
    {
        return [
            'balance' => $this->balance,
            'spendable_balance' => $this->spendableBalance,
            'withdrawable_balance' => $this->withdrawableBalance,
            'charges' => $this->charges,
            'payments' => $this->payments,
            'deducts' => $this->deducts,
            'refunds' => $this->refunds,
            'transfers_in' => $this->transfersIn,
            'transfers_out' => $this->transfersOut,
            'from' => $this->from?->toIso8601String(),
            'to' => $this->to?->toIso8601String(),
        ];
    }
}
