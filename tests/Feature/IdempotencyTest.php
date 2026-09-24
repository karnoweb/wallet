<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\IdempotencyConflict;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 21 of TEST-SCENARIOS.md: "Idempotency".
 */
class IdempotencyTest extends TestCase
{
    /** I001 */
    public function test_same_key_and_same_payload_produces_one_financial_effect(): void
    {
        $user = User::create(['name' => 'Alice']);

        $options = ['idempotency_key' => 'charge:1'];
        $first = $user->charge(500_000, $options);
        $second = $user->charge(500_000, $options);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(500_000, $user->balance());
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    /** I002 */
    public function test_same_key_different_amount_throws_conflict(): void
    {
        $user = User::create(['name' => 'Alice']);

        $user->charge(500_000, ['idempotency_key' => 'charge:2']);

        $this->expectException(IdempotencyConflict::class);
        $user->charge(600_000, ['idempotency_key' => 'charge:2']);
    }

    /** I003 */
    public function test_same_key_different_transfer_destination_throws_conflict(): void
    {
        $source = User::create(['name' => 'Source']);
        $destinationA = User::create(['name' => 'DestinationA']);
        $destinationB = User::create(['name' => 'DestinationB']);
        $source->charge(500_000);

        $source->transferTo($destinationA, 100_000, ['idempotency_key' => 'transfer:1']);

        $this->expectException(IdempotencyConflict::class);
        $source->transferTo($destinationB, 100_000, ['idempotency_key' => 'transfer:1']);
    }

    /** I004 */
    public function test_same_key_different_grant_rules_throws_conflict(): void
    {
        $organization = \Karnoweb\Wallet\Tests\Support\Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);
        $organization->charge(500_000);

        $organization->grantTo($employee, 100_000, ['allowed_club_ids' => [1]], ['idempotency_key' => 'grant:1']);

        $this->expectException(IdempotencyConflict::class);
        $organization->grantTo($employee, 100_000, ['allowed_club_ids' => [2]], ['idempotency_key' => 'grant:1']);
    }

    /** I005 */
    public function test_null_idempotency_key_is_allowed_when_not_required(): void
    {
        $user = User::create(['name' => 'Alice']);

        $charge = $user->charge(500_000);

        $this->assertSame(500_000, $charge->amount);
    }

    /** I006 */
    public function test_required_idempotency_key_missing_fails_before_any_write(): void
    {
        $user = User::create(['name' => 'Alice']);

        try {
            $user->charge(500_000, ['idempotency_required' => true]);
            $this->fail('Expected IdempotencyConflict to be thrown.');
        } catch (IdempotencyConflict $e) {
            // expected
        }

        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('wallet_operations', 0);
    }
}
