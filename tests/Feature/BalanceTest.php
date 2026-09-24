<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class BalanceTest extends TestCase
{
    /** B001 */
    public function test_balance_reflects_charge_and_payment(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000);
        $user->pay(300_000);

        $this->assertSame(700_000, $user->balance());
    }

    /** B002 */
    public function test_balance_uses_a_single_sql_aggregate_query(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(1_000_000);
        $user->charge(500_000);
        $user->pay(200_000);

        $wallet = $user->wallet();

        DB::enableQueryLog();
        $balance = $wallet->balance();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(1_300_000, $balance);
        $this->assertCount(1, $queries);
        $this->assertStringContainsStringIgnoringCase('sum(', $queries[0]['query']);
    }
}
