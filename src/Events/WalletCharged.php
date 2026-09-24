<?php

namespace Karnoweb\Wallet\Events;

use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Dispatched after a successful, committed charge. Carries both the
 * credit transaction and the credit it created so an Accounting
 * Integration can post an entry without recalculating business rules.
 */
class WalletCharged
{
    public function __construct(
        public readonly WalletTransaction $transaction,
        public readonly WalletCredit $credit,
    ) {
    }
}
