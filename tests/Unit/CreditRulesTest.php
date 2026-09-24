<?php

namespace Karnoweb\Wallet\Tests\Unit;

use Karnoweb\Wallet\DTOs\CreditRules;
use Karnoweb\Wallet\Enums\CreditExpireAction;
use PHPUnit\Framework\TestCase;

class CreditRulesTest extends TestCase
{
    public function test_defaults_are_unrestricted_and_expire_action_none(): void
    {
        $rules = CreditRules::fromArray([]);

        $this->assertSame([], $rules->allowedClubIds);
        $this->assertSame([], $rules->allowedServiceIds);
        $this->assertNull($rules->startsAt);
        $this->assertNull($rules->expiresAt);
        $this->assertSame(CreditExpireAction::None, $rules->expireAction);
        $this->assertNull($rules->cashWithdrawable);
    }

    public function test_an_already_built_instance_passes_through_unchanged(): void
    {
        $rules = new CreditRules(allowedClubIds: [1, 2]);

        $this->assertSame($rules, CreditRules::fromArray($rules));
    }

    public function test_ids_are_cast_to_integers_and_expire_action_string_is_parsed(): void
    {
        $rules = CreditRules::fromArray([
            'allowed_club_ids' => ['1', '2'],
            'allowed_service_ids' => ['10'],
            'expire_action' => 'return',
            'cash_withdrawable' => 0,
        ]);

        $this->assertSame([1, 2], $rules->allowedClubIds);
        $this->assertSame([10], $rules->allowedServiceIds);
        $this->assertSame(CreditExpireAction::Return, $rules->expireAction);
        $this->assertFalse($rules->cashWithdrawable);
    }

    public function test_round_trips_through_to_array(): void
    {
        $rules = CreditRules::fromArray([
            'allowed_club_ids' => [1],
            'expire_action' => 'burn',
            'cash_withdrawable' => true,
        ]);

        $array = $rules->toArray();

        $this->assertSame([1], $array['allowed_club_ids']);
        $this->assertSame('burn', $array['expire_action']);
        $this->assertTrue($array['cash_withdrawable']);
    }
}
