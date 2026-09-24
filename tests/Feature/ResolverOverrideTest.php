<?php

namespace Karnoweb\Wallet\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Contracts\CauserResolver;
use Karnoweb\Wallet\Services\ExpirationService;
use Karnoweb\Wallet\Tests\Support\User;
use Karnoweb\Wallet\Tests\TestCase;

/**
 * Section 28 of TEST-SCENARIOS.md: "Resolver overrides".
 */
class ResolverOverrideTest extends TestCase
{
    /** R001 */
    public function test_a_custom_causer_resolver_is_used_when_no_explicit_option_is_given(): void
    {
        $this->app->bind(CauserResolver::class, fn () => new class implements CauserResolver {
            public function resolve(?Model $owner = null, array $options = []): ?int
            {
                return 900;
            }
        });

        $user = User::create(['name' => 'Alice']);
        $transaction = $user->charge(500_000);

        $this->assertSame(900, $transaction->causer_id);
    }

    /** R002 */
    public function test_an_explicit_causer_option_beats_the_custom_resolver(): void
    {
        $this->app->bind(CauserResolver::class, fn () => new class implements CauserResolver {
            public function resolve(?Model $owner = null, array $options = []): ?int
            {
                return 900;
            }
        });

        $user = User::create(['name' => 'Alice']);
        $transaction = $user->charge(500_000, ['causer_id' => 901]);

        $this->assertSame(901, $transaction->causer_id);
    }

    /** R003 */
    public function test_background_operations_allow_a_null_causer(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->charge(500_000, ['rules' => [
            'expires_at' => now()->subMinute(),
            'expire_action' => 'burn',
        ]]);

        // The expiration flow never resolves a causer: no auth context,
        // no explicit option, yet the operation succeeds with a null one.
        $summary = $this->app->make(ExpirationService::class)->expireDueCredits(500);

        $this->assertSame(1, $summary['processed']);

        $burnTransaction = $user->wallet()->transactions()->where('type', 'system')->first();
        $this->assertNull($burnTransaction->causer_id);
    }
}
