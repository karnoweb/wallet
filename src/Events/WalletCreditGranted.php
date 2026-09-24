<?php

namespace Karnoweb\Wallet\Events;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletTransaction;

class WalletCreditGranted
{
    /**
     * @param  Collection<int, \Karnoweb\Wallet\Models\WalletCredit>  $destinationCredits
     */
    public function __construct(
        public readonly WalletTransaction $sourceTransaction,
        public readonly WalletTransaction $destinationTransaction,
        public readonly Collection $destinationCredits,
    ) {
    }
}
