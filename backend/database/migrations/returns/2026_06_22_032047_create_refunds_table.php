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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('amount', 12, 2);
            $table->string('status', 50)->default('pending');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('return_request_id');
            $table->index('payment_method_id');
            $table->index('status');
            $table->index('refunded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
