<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Wallet\Services\BalanceService;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * A Wallet is a container owned by any Eloquent model. One owner has
 * exactly one wallet. It is NOT owned by a club/branch: `club_id` on
 * this table is a legacy-only, deprecated column kept for backward
 * compatibility with the pre-package schema.
 *
 * All financial logic is delegated to package services; this model only
 * exposes convenience read helpers.
 */
class Wallet extends Model
{
    use SoftDeletes;

    protected $table = 'wallets';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'club_id',
        'extra_attributes',
    ];

    protected $casts = [
        'extra_attributes' => 'array',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ConfiguredModels::transaction(), 'wallet_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ConfiguredModels::credit(), 'wallet_id');
    }

    /**
     * Total effective balance: SUM(amount * sign) over this wallet's
     * transactions, computed via SQL aggregate (see BalanceService).
     */
    public function balance(): int
    {
        return app(BalanceService::class)->balance($this);
    }

    public function spendableBalance(array|\Karnoweb\Wallet\DTOs\WalletContext $context = []): int
    {
        return app(BalanceService::class)->spendableBalance($this, $context);
    }

    public function withdrawableBalance(array|\Karnoweb\Wallet\DTOs\WalletContext $context = []): int
    {
        return app(BalanceService::class)->withdrawableBalance($this, $context);
    }
}
