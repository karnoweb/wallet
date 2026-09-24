<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Tests\Support\CustomAllocation;
use Karnoweb\Wallet\Tests\Support\CustomCredit;
use Karnoweb\Wallet\Tests\Support\CustomTransaction;
use Karnoweb\Wallet\Tests\Support\CustomWallet;
use Karnoweb\Wallet\Tests\Support\Organization;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 27 of TEST-SCENARIOS.md: "Configurable models".
 */
class ConfigurableModelsTest extends TestCase
{
    /** CM001 */
    public function test_custom_wallet_model_is_used_for_auto_creation(): void
    {
        config(['wallet.models.wallet' => CustomWallet::class]);

        $user = User::create(['name' => 'Alice']);
        $wallet = $user->wallet();

        $this->assertInstanceOf(CustomWallet::class, $wallet);
    }

    /** CM002 */
    public function test_custom_transaction_model_is_used_for_created_transactions(): void
    {
        config(['wallet.models.transaction' => CustomTransaction::class]);

        $user = User::create(['name' => 'Alice']);
        $transaction = $user->charge(500_000);

        $this->assertInstanceOf(CustomTransaction::class, $transaction);
    }

    /** CM003 */
    public function test_custom_credit_and_allocation_models_are_used(): void
    {
        config([
            'wallet.models.credit' => CustomCredit::class,
            'wallet.models.allocation' => CustomAllocation::class,
        ]);

        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000);
        $payment = $user->pay(200_000);

        $credit = $user->wallet()->credits()->first();
        $allocation = $payment->allocations()->first();

        $this->assertInstanceOf(CustomCredit::class, $credit);
        $this->assertInstanceOf(CustomAllocation::class, $allocation);
    }

    /** CM004 */
    public function test_the_test_application_never_relies_on_app_namespace_models(): void
    {
        $this->assertStringStartsNotWith('App\\', User::class);
        $this->assertStringStartsNotWith('App\\', Organization::class);
    }
}
