<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class GrantTest extends TestCase
{
    /** G001 */
    public function test_simple_organization_grant_moves_value_and_preserves_lineage(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);

        $organization->charge(1_000_000);
        $organization->grantTo($employee, 400_000);

        $this->assertSame(600_000, $organization->balance());
        $this->assertSame(400_000, $employee->balance());

        $employeeCredit = $employee->wallet()->credits()->first();
        $orgCredit = $organization->wallet()->credits()->first();
        $this->assertSame($orgCredit->id, $employeeCredit->parent_credit_id);
        $this->assertSame($organization->wallet()->id, $employeeCredit->source_wallet_id);
    }

    /** G002 */
    public function test_grant_restricted_to_a_club_stores_the_club_scope(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);
        $organization->charge(1_000_000);

        $organization->grantTo($employee, 400_000, ['allowed_club_ids' => [1]]);

        $credit = $employee->wallet()->credits()->first();
        $scopes = $credit->scopes()->where('scope_type', 'club')->pluck('scope_id')->all();
        $this->assertSame([1], $scopes);
    }

    /** G003 */
    public function test_grant_restricted_to_services_stores_exact_scope_rows(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);
        $organization->charge(1_000_000);

        $organization->grantTo($employee, 400_000, ['allowed_service_ids' => [10, 20]]);

        $credit = $employee->wallet()->credits()->first();
        $scopes = $credit->scopes()->where('scope_type', 'service')->pluck('scope_id')->sort()->values()->all();
        $this->assertSame([10, 20], $scopes);
    }

    /** G004 */
    public function test_combined_rules_are_stored_as_an_exact_snapshot(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);
        $organization->charge(1_000_000);

        $expiresAt = now()->addDays(30);

        $organization->grantTo($employee, 400_000, [
            'allowed_club_ids' => [1],
            'allowed_service_ids' => [10, 20],
            'expires_at' => $expiresAt,
            'expire_action' => 'return',
            'cash_withdrawable' => false,
        ]);

        $credit = $employee->wallet()->credits()->first();

        $this->assertSame([1], $credit->scopes()->where('scope_type', 'club')->pluck('scope_id')->all());
        $this->assertSame([10, 20], $credit->scopes()->where('scope_type', 'service')->pluck('scope_id')->sort()->values()->all());
        $this->assertSame('return', $credit->expire_action->value);
        $this->assertFalse($credit->cash_withdrawable);
        $this->assertSame($expiresAt->toDateTimeString(), $credit->expires_at->toDateTimeString());
    }

    /** G005 */
    public function test_changing_the_organization_afterwards_does_not_alter_existing_grant(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);
        $organization->charge(1_000_000);

        $organization->grantTo($employee, 400_000, ['allowed_club_ids' => [1]]);

        $organization->name = 'Renamed Org';
        $organization->save();

        $credit = $employee->wallet()->credits()->first();
        $this->assertSame([1], $credit->scopes()->where('scope_type', 'club')->pluck('scope_id')->all());
    }

    /** G006 */
    public function test_grant_from_multiple_source_clubs_preserves_lineage_for_each(): void
    {
        $organization = Organization::create(['name' => 'Org']);
        $employee = User::create(['name' => 'Employee']);

        $organization->charge(600_000, ['club_id' => 1]);
        $organization->charge(400_000, ['club_id' => 2]);

        $organization->grantTo($employee, 1_000_000);

        $destinationCredits = $employee->wallet()->credits()->orderBy('id')->get();
        $this->assertCount(2, $destinationCredits);

        $this->assertSame(1, $destinationCredits[0]->parentCredit->sourceTransaction->club_id);
        $this->assertSame(2, $destinationCredits[1]->parentCredit->sourceTransaction->club_id);
    }
}
