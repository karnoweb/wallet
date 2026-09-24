<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Wallet\Services\ExpirationService;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 33 of TEST-SCENARIOS.md: "Performance tests". These are
 * lightweight query-count checks, not load/benchmark tests.
 */
class PerformanceTest extends TestCase
{
    /** PF001 */
    public function test_balance_uses_a_single_sql_aggregate_query_regardless_of_transaction_count(): void
    {
        $user = User::create(['name' => 'Alice']);

        for ($i = 0; $i < 20; $i++) {
            $user->charge(10_000);
        }

        $wallet = $user->wallet();

        DB::enableQueryLog();
        $wallet->balance();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $count);
    }

    /** PF002 */
    public function test_statement_is_paginated_and_never_loads_an_unbounded_collection(): void
    {
        $user = User::create(['name' => 'Alice']);

        for ($i = 0; $i < 50; $i++) {
            $user->charge(1_000);
        }

        $statement = $user->statement(['per_page' => 10]);

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $statement);
        $this->assertCount(10, $statement->items());
        $this->assertSame(50, $statement->total());
    }

    /** PF003 */
    public function test_payment_query_count_does_not_grow_linearly_with_credit_scopes(): void
    {
        $user = User::create(['name' => 'Alice']);

        for ($i = 0; $i < 5; $i++) {
            $user->charge(100_000, ['rules' => ['allowed_service_ids' => [10, 20, 30]]]);
        }

        DB::enableQueryLog();
        $user->pay(500_000, [
            'segments' => [['amount' => 500_000, 'scopes' => ['service' => [10]]]],
        ]);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Candidate selection stays O(1) SQL round-trips (filter in DB +
        // eager scopes + lock). Per-credit writes (allocation insert +
        // remaining decrement) scale with consumed credits, not with the
        // total number of unrelated credits/scopes on the wallet.
        $this->assertLessThan(35, $count);
    }

    /** PF004 */
    public function test_expiration_processes_a_large_due_set_in_configured_chunks(): void
    {
        $user = User::create(['name' => 'Alice']);

        for ($i = 0; $i < 7; $i++) {
            $user->charge(10_000, ['rules' => [
                'expires_at' => now()->subMinute(),
                'expire_action' => 'burn',
            ]]);
        }

        $summary = $this->app->make(ExpirationService::class)->expireDueCredits(3); // small chunk size

        $this->assertSame(7, $summary['processed']);
        $this->assertSame(0, $user->balance());
    }
}
