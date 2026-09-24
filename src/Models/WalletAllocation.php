<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Wallet\Enums\WalletAllocationType;
use Karnoweb\Wallet\Exceptions\ImmutableWalletAllocation;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Immutable record of which credit funded a debit transaction ("consume"),
 * or which prior allocation was restored by a refund/return ("restore").
 */
class WalletAllocation extends Model
{
    protected $table = 'wallet_allocations';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'wallet_transaction_id',
        'wallet_credit_id',
        'amount',
        'type',
        'original_allocation_id',
        'segment_key',
    ];

    protected $casts = [
        'amount' => 'integer',
        'type' => WalletAllocationType::class,
        'wallet_transaction_id' => 'integer',
        'wallet_credit_id' => 'integer',
        'original_allocation_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $allocation) {
            if (! $allocation->created_at) {
                $allocation->created_at = now();
            }
        });

        static::updating(function () {
            throw ImmutableWalletAllocation::make('updated');
        });

        static::deleting(function () {
            throw ImmutableWalletAllocation::make('deleted');
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::transaction(), 'wallet_transaction_id');
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::credit(), 'wallet_credit_id');
    }

    public function originalAllocation(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::allocation(), 'original_allocation_id');
    }

    public function restores(): HasMany
    {
        return $this->hasMany(ConfiguredModels::allocation(), 'original_allocation_id');
    }
}
