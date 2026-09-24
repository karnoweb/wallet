<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * A single eligibility restriction snapshot on a WalletCredit, e.g.
 * ('club', 1) or ('service', 10). Absence of scopes of a given type means
 * the credit is unrestricted for that type.
 */
class WalletCreditScope extends Model
{
    protected $table = 'wallet_credit_scopes';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'wallet_credit_id',
        'scope_type',
        'scope_id',
    ];

    protected $casts = [
        'wallet_credit_id' => 'integer',
        'scope_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $scope) {
            if (! $scope->created_at) {
                $scope->created_at = now();
            }
        });
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::credit(), 'wallet_credit_id');
    }
}
