<?php

namespace Karnoweb\Wallet\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Concerns\HasWallets;

/**
 * Generic host-application fixture model. Deliberately NOT named
 * `App\Models\User` and defined outside `App\Models` to prove the
 * package has no hard dependency on any host application namespace
 * (CM004).
 */
class User extends Model
{
    use HasWallets;

    protected $table = 'users';

    protected $fillable = ['name'];
}
