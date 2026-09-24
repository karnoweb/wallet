<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 31 of TEST-SCENARIOS.md: "Legacy migration scenarios".
 *
 * Per the implementation blueprint (section 30, "Legacy production
 * migration plan"), the actual migration tooling — reading legacy
 * per-club wallet tables, merging them into one canonical wallet,
 * choosing a canonical wallet, auditing external references — is
 * explicitly the HOST APPLICATION's responsibility ("Implement
 * migration support in host application"). This package ships no
 * migration command/class of its own.
 *
 * These tests therefore only verify, using the package's existing
 * public primitives, that the *target state* a host migration would
 * produce is representable and passes the package's own consistency
 * checks (reconciliation). They cannot exercise M001/M003/M004/M005/
 * M006/M007 (multi-wallet source data, canonical-wallet selection,
 * external reference auditing), since no legacy source data model or
 * migration tool exists inside this package to drive them.
 */
class LegacyMigrationTest extends TestCase
{
    /** M002 (target state) */
    public function test_merging_two_legacy_club_wallets_into_one_canonical_wallet_preserves_club_lineage(): void
    {
        $user = User::create(['name' => 'Alice']);

        // Simulates the opening credits a host migration would create on
        // the new canonical wallet for two legacy per-club wallets.
        $user->charge(500_000, ['club_id' => 1]);
        $user->charge(300_000, ['club_id' => 2]);

        $this->assertSame(800_000, $user->balance());

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(1, $credits[0]->sourceTransaction->club_id);
        $this->assertSame(500_000, $credits[0]->original_amount);
        $this->assertSame(2, $credits[1]->sourceTransaction->club_id);
        $this->assertSame(300_000, $credits[1]->original_amount);
    }

    /** M008 (target state) */
    public function test_a_negative_legacy_balance_is_preserved_without_a_fake_positive_credit(): void
    {
        $user = User::create(['name' => 'Alice']);

        // A host migration representing a legacy negative balance must
        // use allow_negative rather than invent a positive credit.
        $user->pay(200_000, ['allow_negative' => true]);

        $this->assertSame(-200_000, $user->balance());
        $this->assertSame(0, $user->wallet()->credits()->count());
    }

    /** M009 (target state) */
    public function test_the_post_migration_canonical_wallet_passes_reconciliation(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['club_id' => 1]);
        $user->charge(300_000, ['club_id' => 2]);
        $user->pay(100_000);

        $this->artisan('wallet:reconcile')->assertExitCode(0);
    }
}
