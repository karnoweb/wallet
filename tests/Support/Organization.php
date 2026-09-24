<?php

namespace Karnoweb\Wallet\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Concerns\HasWallets;

/**
 * Fixture model representing a generic wallet owner that is NOT a user
 * (e.g. an organization granting credit to employees). Proves the same
 * Trait works for any Eloquent model.
 */
class Organization extends Model
{
    use HasWallets;

    protected $table = 'organizations';

    protected $fillable = ['name'];
}
