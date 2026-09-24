<?php

namespace Karnoweb\Wallet\Events;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletTransaction;

class WalletDeducted
{
    /**
     * @param  Collection<int, \Karnoweb\Wallet\Models\WalletAllocation>  $allocations
     */
    public function __construct(
        public readonly WalletTransaction $transaction,
        public readonly Collection $allocations,
    ) {
    }
}
