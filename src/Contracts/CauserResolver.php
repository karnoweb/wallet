<?php

namespace Karnoweb\Wallet\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the causer id (the actor who triggered the operation) for a
 * wallet transaction. No hard FK to a host `users` table exists; a null
 * causer is allowed for background/system operations.
 */
interface CauserResolver
{
    public function resolve(?Model $owner = null, array $options = []): ?int;
}
