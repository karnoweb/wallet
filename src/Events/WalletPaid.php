<?php

namespace Karnoweb\Wallet\Events;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Dispatched after a successful, committed payment. `$allocations`
 * carries, for every consumed credit, its source_transaction/club and
 * lineage so the Accounting Integration can derive inter-branch
 * settlement without the Wallet package knowing anything about
 * debit/credit accounts.
 */
class WalletPaid
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
