<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Exceptions\InsufficientBalance;
use Karnoweb\Wallet\Tests\Support\ModelWithSettings;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class SettingsPrecedenceTest extends TestCase
{
    /** S001 */
    public function test_config_default_is_used_when_nothing_overrides_it(): void
    {
        config()->set('wallet.defaults.allow_negative', false);

        $user = User::create(['name' => 'Alice']);

        $this->expectException(InsufficientBalance::class);
        $user->pay(100_000);
    }

    /** S002 */
    public function test_owner_class_config_overrides_defaults(): void
    {
        config()->set('wallet.defaults.allow_negative', false);
        config()->set('wallet.owners.'.User::class.'.allow_negative', true);

        $user = User::create(['name' => 'Alice']);

        $transaction = $user->pay(100_000);

        $this->assertSame(-100_000, $transaction->amount * $transaction->sign);
        $this->assertSame(-100_000, $user->balance());
    }

    /** S003 */
    public function test_model_walletsettings_overrides_owner_config(): void
    {
        config()->set('wallet.owners.'.ModelWithSettings::class.'.allow_negative', false);
        ModelWithSettings::$settingsOverride = ['allow_negative' => true];

        $owner = ModelWithSettings::create(['name' => 'Contract']);

        $transaction = $owner->pay(50_000);

        $this->assertSame(-50_000, $transaction->amount * $transaction->sign);
    }

    /** S004 */
    public function test_operation_option_wins_over_every_other_setting(): void
    {
        config()->set('wallet.defaults.allow_negative', false);
        ModelWithSettings::$settingsOverride = ['allow_negative' => false];

        $owner = ModelWithSettings::create(['name' => 'Contract']);

        $transaction = $owner->pay(25_000, ['allow_negative' => true]);

        $this->assertSame(-25_000, $transaction->amount * $transaction->sign);
    }
}
