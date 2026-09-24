<?php

namespace Karnoweb\Wallet\Events;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Dispatched once for a transfer, carrying both transactions and the
 * lineage between the source allocations and the destination credits
 * created for them.
 */
class WalletTransferred
{
    /**
     * @param  Collection<int, \Karnoweb\Wallet\Models\WalletAllocation>  $sourceAllocations
     * @param  Collection<int, WalletCredit>  $destinationCredits
     */
    public function __construct(
        public readonly WalletTransaction $sourceTransaction,
        public readonly WalletTransaction $destinationTransaction,
        public readonly Collection $sourceAllocations,
        public readonly Collection $destinationCredits,
    ) {
    }
}
