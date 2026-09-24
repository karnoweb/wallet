<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Karnoweb\Wallet\Enums\WalletTransactionType;
use Karnoweb\Wallet\Exceptions\ImmutableWalletTransaction;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * An immutable financial movement against a Wallet. Once created, a
 * transaction can never be updated or deleted through the Eloquent model
 * API: `updating`/`deleting` events always throw.
 *
 * The club/branch of the operation belongs here (not on the Wallet).
 */
class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    public $timestamps = true;

    protected $fillable = [
        'wallet_id',
        'causer_id',
        'transaction_id',
        'amount',
        'sign',
        'type',
        'transactionable_type',
        'transactionable_id',
        'description',
        'operation_id',
        'club_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'sign' => 'integer',
        'type' => WalletTransactionType::class,
        'wallet_id' => 'integer',
        'operation_id' => 'integer',
        'club_id' => 'integer',
        'causer_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw ImmutableWalletTransaction::make('updated');
        });

        static::deleting(function () {
            throw ImmutableWalletTransaction::make('deleted');
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::wallet(), 'wallet_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::operation(), 'operation_id');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ConfiguredModels::allocation(), 'wallet_transaction_id');
    }
}
