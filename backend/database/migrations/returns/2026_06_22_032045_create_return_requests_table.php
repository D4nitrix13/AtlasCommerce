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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('reason');
            $table->string('status', 50)->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('requested_at');
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
