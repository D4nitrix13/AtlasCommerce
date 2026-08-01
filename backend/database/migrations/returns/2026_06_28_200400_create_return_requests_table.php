<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
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

            $table->index(['store_id', 'status'], 'return_requests_store_status_idx');
            $table->index(['order_id'], 'return_requests_order_id_idx');
            $table->index(['customer_id'], 'return_requests_customer_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
