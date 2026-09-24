<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Karnoweb\Wallet\Contracts\ClubResolver;
use Karnoweb\Wallet\Exceptions\ClubRequired;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

class ClubRequirementTest extends TestCase
{
    /** CR001 */
    public function test_club_is_optional_by_default(): void
    {
        config()->set('wallet.defaults.club_required', false);

        $user = User::create(['name' => 'Alice']);

        $transaction = $user->charge(100_000);

        $this->assertNull($transaction->club_id);
    }

    /** CR002 */
    public function test_club_required_setting_throws_when_unresolved(): void
    {
        \Karnoweb\Wallet\Tests\Support\ModelWithSettings::$settingsOverride = ['club_required' => true];
        $owner = \Karnoweb\Wallet\Tests\Support\ModelWithSettings::create(['name' => 'Contract']);

        $this->expectException(ClubRequired::class);
        $owner->charge(100_000);
    }

    /** CR003 */
    public function test_custom_club_resolver_supplies_club_when_not_explicit(): void
    {
        $this->app->bind(ClubResolver::class, fn () => new class implements ClubResolver {
            public function resolve(?\Illuminate\Database\Eloquent\Model $owner = null, array $options = []): ?int
            {
                return $options['club_id'] ?? 50;
            }
        });

        $user = User::create(['name' => 'Alice']);

        $transaction = $user->charge(100_000);

        $this->assertSame(50, $transaction->club_id);
    }

    /** CR004 */
    public function test_explicit_club_option_beats_custom_resolver(): void
    {
        $this->app->bind(ClubResolver::class, fn () => new class implements ClubResolver {
            public function resolve(?\Illuminate\Database\Eloquent\Model $owner = null, array $options = []): ?int
            {
                return $options['club_id'] ?? 50;
            }
        });

        $user = User::create(['name' => 'Alice']);

        $transaction = $user->charge(100_000, ['club_id' => 60]);

        $this->assertSame(60, $transaction->club_id);
    }
}
