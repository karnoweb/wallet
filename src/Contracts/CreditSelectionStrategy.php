<?php

namespace Karnoweb\Wallet\Contracts;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Models\WalletCredit;

/**
 * Decides which already-filtered/eligible candidate credits fund a given
 * amount, and in what order/portions. Implementations MUST NOT mutate the
 * database or touch `remaining_amount` themselves: locking and mutation
 * belong to `AllocationService`. This keeps the strategy replaceable.
 */
interface CreditSelectionStrategy
{
    /**
     * @param  Collection<int, WalletCredit>  $candidates  Already-filtered eligible credits.
     * @param  int  $amountRequired  Amount that needs to be funded from these candidates.
     * @return Collection<int, array{credit: WalletCredit, amount: int}> Planned allocations, in the order they should be consumed.
     */
    public function plan(Collection $candidates, int $amountRequired): Collection;
}
