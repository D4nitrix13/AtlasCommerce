<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('payment_provider', 80);
            $table->string('transaction_code', 160)->nullable();
            $table->longText('provider_response')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('transacted_at')->useCurrent();

            $table->timestamps();

            $table->index('payment_id');
            $table->index('payment_provider');
            $table->index('transaction_code');
            $table->index('status');
            $table->index('transacted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
