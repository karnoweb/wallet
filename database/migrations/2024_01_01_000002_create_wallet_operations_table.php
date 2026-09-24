<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_operations', function (Blueprint $table) {
            $table->id();

            $table->string('type', 32);
            $table->string('idempotency_key', 128)->nullable();
            $table->char('payload_hash', 64)->nullable();

            $table->timestamps();

            $table->unique(['type', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_operations');
    }
};
