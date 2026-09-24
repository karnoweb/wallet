<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InvalidRefund;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class RefundTest extends TestCase
{
    /** RF001 */
    public function test_full_refund_restores_the_entire_payment(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);

        $refund = $user->refund($payment);

        $this->assertSame(200_000, $refund->amount);
        $this->assertSame(500_000, $user->balance());
        $this->assertSame(500_000, $user->wallet()->credits()->first()->remaining_amount);
    }

    /** RF002 */
    public function test_refund_of_a_restricted_credit_preserves_its_restrictions(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['rules' => ['allowed_service_ids' => [10]]]);

        $payment = $user->pay(200_000, [
            'segments' => [['amount' => 200_000, 'scopes' => ['service' => [10]]]],
        ]);

        $user->refund($payment);

        $credit = $user->wallet()->credits()->first();
        $this->assertSame(500_000, $credit->remaining_amount);
        $this->assertSame([10], $credit->scopes()->where('scope_type', 'service')->pluck('scope_id')->all());

        // Still usable only for service 10 after the refund.
        $secondPayment = $user->pay(300_000, [
            'segments' => [['amount' => 300_000, 'scopes' => ['service' => [10]]]],
        ]);
        $this->assertSame(300_000, $secondPayment->amount);
    }

    /** RF003 */
    public function test_full_refund_across_multiple_credits_restores_all_of_them(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(300_000);
        $user->charge(400_000);

        $payment = $user->pay(500_000); // drains credit A, takes 200_000 from B

        $user->refund($payment);

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(300_000, $credits[0]->remaining_amount);
        $this->assertSame(400_000, $credits[1]->remaining_amount);
    }

    /** RF004 */
    public function test_partial_refund_restores_allocations_in_original_allocation_id_order(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(200_000);
        $user->charge(200_000);

        $payment = $user->pay(300_000); // 200_000 from credit A, 100_000 from credit B

        $user->refund($payment, 200_000); // should restore all of A's allocation first

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(200_000, $credits[0]->remaining_amount); // fully restored
        $this->assertSame(100_000, $credits[1]->remaining_amount); // untouched, still consumed
    }

    /** RF005 */
    public function test_partial_refund_can_cross_multiple_allocations(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(300_000);
        $user->charge(200_000);

        $payment = $user->pay(500_000); // 300_000 from A, 200_000 from B

        $user->refund($payment, 400_000); // fully restores A (300_000) + partial B (100_000)

        $credits = $user->wallet()->credits()->orderBy('id')->get();
        $this->assertSame(300_000, $credits[0]->remaining_amount);
        $this->assertSame(100_000, $credits[1]->remaining_amount);
    }

    /** RF006 */
    public function test_refund_exceeding_refundable_amount_is_rejected(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);

        $this->expectException(InvalidRefund::class);
        $user->refund($payment, 300_000);
    }

    /** RF007 */
    public function test_refund_is_idempotent_for_the_same_key(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);

        $options = ['idempotency_key' => 'refund:1'];

        $first = $user->refund($payment, 200_000, $options);
        $second = $user->refund($payment, 200_000, $options);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(500_000, $user->balance());
    }
}
