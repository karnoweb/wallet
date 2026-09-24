<?php

namespace Karnoweb\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Wallet\Enums\WalletOperationType;
use Karnoweb\Wallet\Support\ConfiguredModels;

/**
 * Idempotency envelope shared by one or more wallet transactions created
 * within a single logical operation (e.g. a transfer creates two
 * transactions under one operation).
 */
class WalletOperation extends Model
{
    protected $table = 'wallet_operations';

    protected $fillable = [
        'type',
        'idempotency_key',
        'payload_hash',
    ];

    protected $casts = [
        'type' => WalletOperationType::class,
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(ConfiguredModels::transaction(), 'operation_id');
    }
}
