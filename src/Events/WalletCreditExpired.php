<?php

namespace Karnoweb\Wallet\Events;

use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletTransaction;

class WalletCreditExpired
{
    public function __construct(
        public readonly WalletCredit $credit,
        public readonly ?WalletTransaction $transaction,
        public readonly string $action,
    ) {
    }
}
