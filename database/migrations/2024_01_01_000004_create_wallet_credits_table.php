<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_credits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('source_transaction_id');
            $table->unsignedBigInteger('parent_credit_id')->nullable();
            $table->unsignedBigInteger('source_wallet_id')->nullable();

            $table->unsignedBigInteger('original_amount');
            $table->unsignedBigInteger('remaining_amount');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('expire_action', 20)->default('none');
            $table->boolean('cash_withdrawable')->default(true);

            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('source_transaction_id')->references('id')->on('wallet_transactions')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('parent_credit_id')->references('id')->on('wallet_credits')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('source_wallet_id')->references('id')->on('wallets')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index('wallet_id');
            $table->index('source_transaction_id');
            $table->index('parent_credit_id');
            $table->index('source_wallet_id');
            $table->index(['wallet_id', 'created_at', 'id']);
            $table->index(['wallet_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_credits');
    }
};
