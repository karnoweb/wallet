<?php

namespace Karnoweb\Wallet\Tests\Support;

use Illuminate\Support\Collection;
use Karnoweb\Wallet\Contracts\CreditSelectionStrategy;

/**
 * Test double that always throws so callers can assert financial writes
 * and WalletOperation rows roll back together.
 */
class ThrowingCreditSelectionStrategy implements CreditSelectionStrategy
{
    public function plan(Collection $candidates, int $amountRequired): Collection
    {
        throw new \RuntimeException('forced allocation failure');
    }
}
