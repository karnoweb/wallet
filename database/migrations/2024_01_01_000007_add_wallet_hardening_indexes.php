<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening indexes / constraints for production-safe concurrency and
 * credit selection. Does not rewrite the original create migrations.
 *
 * Owner→wallet uniqueness is intentionally NOT added here: wallets use
 * SoftDeletes and legacy rows may still carry per-club duplicates via
 * deprecated club_id. Application-level createWalletWithLock remains the
 * invariant enforcer for new installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_credits', function (Blueprint $table) {
            // Speeds FIFO candidate selection: wallet + remaining + time window.
            $table->index(
                ['wallet_id', 'remaining_amount', 'expires_at'],
                'wallet_credits_wallet_remaining_expires_index'
            );
        });

        Schema::table('wallet_credit_scopes', function (Blueprint $table) {
            // Speeds whereHas/whereDoesntHave club/service filters.
            $table->index(
                ['scope_type', 'scope_id'],
                'wallet_credit_scopes_type_id_index'
            );
        });

        Schema::table('wallet_allocations', function (Blueprint $table) {
            // Speeds refund restore-total aggregation by original allocation.
            $table->index(
                ['type', 'original_allocation_id'],
                'wallet_allocations_type_original_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('wallet_credits', function (Blueprint $table) {
            $table->dropIndex('wallet_credits_wallet_remaining_expires_index');
        });

        Schema::table('wallet_credit_scopes', function (Blueprint $table) {
            $table->dropIndex('wallet_credit_scopes_type_id_index');
        });

        Schema::table('wallet_allocations', function (Blueprint $table) {
            $table->dropIndex('wallet_allocations_type_original_index');
        });
    }
};
