<?php

namespace Karnoweb\Wallet\Support;

use Illuminate\Support\Facades\DB;

/**
 * Runs wallet financial work in a DB transaction.
 *
 * On MySQL/MariaDB the next transaction is started at READ COMMITTED so a
 * concurrent loser of a unique idempotency insert can SELECT the winner's
 * committed WalletOperation. Under the default REPEATABLE READ snapshot,
 * that SELECT would keep seeing "not found" and surface a duplicate-key
 * error to the caller.
 */
final class AtomicWalletTransaction
{
    public static function run(callable $callback): mixed
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        return $connection->transaction($callback);
    }
}
