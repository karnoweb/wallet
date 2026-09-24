<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class TransferTest extends TestCase
{
    /** T001 */
    public function test_transfer_one_credit_preserves_lineage(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);

        $source->charge(500_000);

        $result = $source->transferTo($destination, 200_000);

        $this->assertSame(300_000, $source->balance());
        $this->assertSame(300_000, $source->wallet()->credits()->first()->remaining_amount);

        $this->assertSame(200_000, $destination->balance());
        $destinationCredit = $destination->wallet()->credits()->first();
        $this->assertSame(200_000, $destinationCredit->remaining_amount);
        $this->assertSame($source->wallet()->credits()->first()->id, $destinationCredit->parent_credit_id);
        $this->assertSame($source->wallet()->id, $destinationCredit->source_wallet_id);

        $this->assertSame($result->sourceTransaction->wallet_id, $source->wallet()->id);
        $this->assertSame($result->destinationTransaction->wallet_id, $destination->wallet()->id);
    }

    /** T002 */
    public function test_transfer_from_multiple_source_credits_creates_multiple_destination_credits(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);

        $creditA = $source->charge(300_000);
        $creditB = $source->charge(400_000);

        $source->transferTo($destination, 500_000);

        $destinationCredits = $destination->wallet()->credits()->orderBy('id')->get();
        $this->assertCount(2, $destinationCredits);

        $sourceCreditA = $source->wallet()->credits()->where('source_transaction_id', $creditA->id)->first();
        $sourceCreditB = $source->wallet()->credits()->where('source_transaction_id', $creditB->id)->first();

        $this->assertSame($sourceCreditA->id, $destinationCredits[0]->parent_credit_id);
        $this->assertSame(300_000, $destinationCredits[0]->original_amount);

        $this->assertSame($sourceCreditB->id, $destinationCredits[1]->parent_credit_id);
        $this->assertSame(200_000, $destinationCredits[1]->original_amount);
    }

    /** T003 */
    public function test_transfer_is_atomic_when_destination_creation_fails(): void
    {
        $source = User::create(['name' => 'Source']);
        $source->charge(500_000);

        // An unsaved Wallet has no id: writing the destination transaction
        // will violate the not-null wallet_id column, forcing a real
        // failure inside the same DB transaction as the source debit.
        $destinationWallet = new \Karnoweb\Wallet\Models\Wallet([
            'reference_type' => User::class,
            'reference_id' => 999999,
        ]);

        try {
            $source->transferTo($destinationWallet, 200_000);
            $this->fail('Expected the destination-side write to fail.');
        } catch (\Throwable $e) {
            // expected: a database-level failure while saving the
            // destination transaction.
        }

        $this->assertDatabaseCount('wallet_transactions', 1); // only the original charge
        $this->assertSame(500_000, $source->balance());
        $this->assertSame(500_000, $source->wallet()->credits()->first()->remaining_amount);
    }

    /** T004 */
    public function test_transfer_to_the_same_wallet_is_rejected(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);

        $this->expectException(WalletException::class);
        $user->transferTo($user, 100_000);
    }

    /** T005 */
    public function test_transfer_idempotency_prevents_duplicate_effects(): void
    {
        $source = User::create(['name' => 'Source']);
        $destination = User::create(['name' => 'Destination']);
        $source->charge(500_000);

        $options = ['idempotency_key' => 'transfer:1'];

        $first = $source->transferTo($destination, 200_000, $options);
        $second = $source->transferTo($destination, 200_000, $options);

        $this->assertSame($first->sourceTransaction->id, $second->sourceTransaction->id);
        $this->assertSame($first->destinationTransaction->id, $second->destinationTransaction->id);
        $this->assertSame(300_000, $source->balance());
        $this->assertSame(200_000, $destination->balance());
    }
}
