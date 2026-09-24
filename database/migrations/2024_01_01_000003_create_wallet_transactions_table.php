<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_id');

            // No FK to host `users` table by design; the package does not
            // depend on any host application model.
            $table->unsignedBigInteger('causer_id')->nullable();

            // Optional external reference id (e.g. a host payment/order
            // transaction id). Never used for FK constraints.
            $table->string('transaction_id')->nullable();

            $table->unsignedBigInteger('amount');
            $table->smallInteger('sign');
            $table->string('type', 32);

            // Explicit morph columns + short index name: Laravel's
            // nullableMorphs() auto-index exceeds MySQL's 64-char limit
            // (`wallet_transactions_transactionable_type_transactionable_id_index`).
            $table->string('transactionable_type')->nullable();
            $table->unsignedBigInteger('transactionable_id')->nullable();
            $table->index(
                ['transactionable_type', 'transactionable_id'],
                'wallet_tx_transactionable_index'
            );

            $table->text('description')->nullable();

            $table->unsignedBigInteger('operation_id')->nullable();
            $table->unsignedBigInteger('club_id')->nullable();

            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('operation_id')->references('id')->on('wallet_operations')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index('wallet_id');
            $table->index('operation_id');
            $table->index('club_id');
            $table->index('causer_id');
            $table->index('transaction_id');
            $table->index('type');
            $table->index('sign');
            $table->index(['wallet_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
