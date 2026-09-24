<?php

namespace Karnoweb\Wallet\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Contracts\ClubResolver;

/**
 * Default resolution order: `options['club_id']`, then null. The package
 * never calls host-application helpers such as `current_club_id()`; a
 * host application replaces this binding in `config('wallet.resolvers.club')`
 * to integrate with its own "current club" concept.
 */
class DefaultClubResolver implements ClubResolver
{
    public function resolve(?Model $owner = null, array $options = []): ?int
    {
        return isset($options['club_id']) ? (int) $options['club_id'] : null;
    }
}
