<?php

namespace Karnoweb\Wallet\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the club/branch id for a wallet operation. The package never
 * calls host-application helpers directly; a host application replaces
 * this contract's binding in `config('wallet.resolvers.club')` to plug
 * into its own "current club" concept.
 */
interface ClubResolver
{
    public function resolve(?Model $owner = null, array $options = []): ?int;
}
