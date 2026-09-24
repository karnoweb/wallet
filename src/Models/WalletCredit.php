<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Wallet\Enums\CreditExpireAction;
use Karnoweb\Wallet\Exceptions\WalletException;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Records the source, remaining amount, lineage and rules of a slice of a
 * wallet's balance.
 *
 * Only `remaining_amount` is mutated by package runtime logic (allocation
 * consume/restore, expiration). Every other field is a snapshot taken at
 * grant/charge time and must never change afterwards: this is enforced
 * below by rejecting any update that touches a field other than
 * `remaining_amount`.
 */
class WalletCredit extends Model
{
    protected $table = 'wallet_credits';

    protected $fillable = [
        'wallet_id',
        'source_transaction_id',
        'parent_credit_id',
        'source_wallet_id',
        'original_amount',
        'remaining_amount',
        'starts_at',
        'expires_at',
        'expire_action',
        'cash_withdrawable',
    ];

    protected $casts = [
        'original_amount' => 'integer',
        'remaining_amount' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'expire_action' => CreditExpireAction::class,
        'cash_withdrawable' => 'boolean',
        'wallet_id' => 'integer',
        'source_transaction_id' => 'integer',
        'parent_credit_id' => 'integer',
        'source_wallet_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $credit) {
            $mutable = ['remaining_amount', 'updated_at'];

            $dirty = array_keys($credit->getDirty());
            $forbidden = array_diff($dirty, $mutable);

            if (! empty($forbidden)) {
                throw new WalletException(
                    'WalletCredit fields other than remaining_amount are immutable snapshot fields. Attempted to change: '.implode(', ', $forbidden)
                );
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::wallet(), 'wallet_id');
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::transaction(), 'source_transaction_id');
    }

    public function parentCredit(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::credit(), 'parent_credit_id');
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::wallet(), 'source_wallet_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(ConfiguredModels::creditScope(), 'wallet_credit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ConfiguredModels::allocation(), 'wallet_credit_id');
    }
}
