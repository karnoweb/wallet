<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Legacy-only. New package logic MUST NOT use wallets.club_id.
            // Kept nullable and deprecated for backward compatibility with
            // the pre-package one-wallet-per-club schema. Do not remove in
            // the first production migration; see the legacy migration plan.
            $table->unsignedBigInteger('club_id')->nullable()->comment('Deprecated: legacy per-club wallet id. Do not use in new logic.');

            $table->json('extra_attributes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['reference_type', 'reference_id']);
            $table->index('club_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
