<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_credit_scopes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_credit_id');

            // V1 scope types: "club", "service". No FK to host Club/Service
            // tables by design.
            $table->string('scope_type', 50);
            $table->unsignedBigInteger('scope_id');

            $table->timestamp('created_at')->nullable();

            $table->foreign('wallet_credit_id')->references('id')->on('wallet_credits')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->unique(['wallet_credit_id', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_credit_scopes');
    }
};
