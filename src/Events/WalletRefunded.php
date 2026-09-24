<?php

namespace Karnoweb\Wallet\Events;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletTransaction;

class WalletRefunded
{
    /**
     * @param  Collection<int, \Karnoweb\Wallet\Models\WalletAllocation>  $restoreAllocations
     */
    public function __construct(
        public readonly WalletTransaction $transaction,
        public readonly WalletTransaction $originalPayment,
        public readonly Collection $restoreAllocations,
    ) {
    }
}
