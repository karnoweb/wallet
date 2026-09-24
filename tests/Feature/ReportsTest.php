<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 25 of TEST-SCENARIOS.md: "Reports".
 */
class ReportsTest extends TestCase
{
    /** RP001 */
    public function test_statement_default_sort_is_created_at_desc_then_id_desc(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000);
        $user->charge(200_000);
        $user->charge(300_000);

        $statement = $user->statement();
        $ids = collect($statement->items())->pluck('id')->all();

        $this->assertSame(array_reverse($ids), collect($ids)->sort()->values()->all());
    }

    /** RP002 */
    public function test_statement_can_be_filtered_by_date(): void
    {
        $user = User::create(['name' => 'Alice']);
        $old = $user->charge(100_000);
        $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $recent = $user->charge(200_000);

        $statement = $user->statement(['from' => now()->subDay()->toDateTimeString()]);

        $this->assertCount(1, $statement->items());
        $this->assertSame($recent->id, $statement->items()[0]->id);
    }

    /** RP003 */
    public function test_statement_can_be_filtered_by_club(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(100_000, ['club_id' => 1]);
        $user->charge(200_000, ['club_id' => 2]);

        $statement = $user->statement(['club_id' => 1]);

        $this->assertCount(1, $statement->items());
        $this->assertSame(1, $statement->items()[0]->club_id);
    }

    /** RP004 */
    public function test_statement_can_be_filtered_by_type(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $user->pay(100_000);

        $statement = $user->statement(['type' => 'payment']);

        $this->assertCount(1, $statement->items());
        $this->assertSame('payment', $statement->items()[0]->type->value);
    }

    /** RP005 */
    public function test_statement_can_be_filtered_by_transactionable(): void
    {
        $order = \Karnoweb\Wallet\Tests\Support\Order::create(['name' => 'Order #1']);
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['transactionable' => $order]);
        $user->charge(100_000);

        $statement = $user->statement([
            'transactionable_type' => $order->getMorphClass(),
            'transactionable_id' => $order->id,
        ]);

        $this->assertCount(1, $statement->items());
    }

    /** RP006 */
    public function test_requesting_more_than_the_configured_max_per_page_is_capped(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $statement = $user->statement(['per_page' => 10_000]);

        $this->assertSame(
            (int) config('wallet.reports.max_per_page'),
            $statement->perPage()
        );
    }

    /** RP007 */
    public function test_summary_totals_match_known_transactions_exactly(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);

        $source->charge(1_000_000);
        $payment = $source->pay(200_000);
        $source->refund($payment, 50_000);
        $source->deduct(30_000);
        $source->transferTo($destination, 100_000);

        $summary = $source->summary();

        $this->assertSame(1_000_000, $summary->charges);
        $this->assertSame(200_000, $summary->payments);
        $this->assertSame(30_000, $summary->deducts);
        $this->assertSame(50_000, $summary->refunds);
        $this->assertSame(100_000, $summary->transfersOut);
        $this->assertSame(0, $summary->transfersIn);
        $this->assertSame($source->balance(), $summary->balance);
    }
}
