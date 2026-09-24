<?php

namespace Karnoweb\Wallet\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Contracts\CauserResolver;

/**
 * Default resolution order: explicit `options['causer_id']`, then the
 * authenticated user id (when the auth component is available), then
 * null. No hard FK to a host `users` table is required, so background
 * jobs (e.g. expiration) may operate with a null causer.
 */
class DefaultCauserResolver implements CauserResolver
{
    public function resolve(?Model $owner = null, array $options = []): ?int
    {
        if (isset($options['causer_id'])) {
            return (int) $options['causer_id'];
        }

        if (function_exists('auth') && auth()->check()) {
            return (int) auth()->id();
        }

        return null;
    }
}
