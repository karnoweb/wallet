<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_transaction_id');
            $table->unsignedBigInteger('wallet_credit_id');

            $table->unsignedBigInteger('amount');
            $table->string('type', 20);

            $table->unsignedBigInteger('original_allocation_id')->nullable();

            // Opaque to the package. Enables item-level eligibility and
            // exact refunds in advanced (order/segmented) use cases.
            $table->string('segment_key', 191)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('wallet_transaction_id')->references('id')->on('wallet_transactions')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('wallet_credit_id')->references('id')->on('wallet_credits')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('original_allocation_id')->references('id')->on('wallet_allocations')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index('wallet_transaction_id');
            $table->index('wallet_credit_id');
            $table->index('original_allocation_id');
            $table->index('segment_key');
            $table->index(['wallet_transaction_id', 'segment_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_allocations');
    }
};
