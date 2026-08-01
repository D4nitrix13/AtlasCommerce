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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->foreignId('sales_channel_id')
                ->nullable()
                ->constrained('sales_channels')
                ->nullOnDelete();

            $table->foreignId('user_issuer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('invoice_number', 80)->unique();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);

            $table->string('payment_status', 50)->default('pending');
            $table->string('invoice_status', 50)->default('issued');

            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('due_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('customer_id');
            $table->index('store_id');
            $table->index('sales_channel_id');
            $table->index('user_issuer_id');
            $table->index('invoice_number');
            $table->index('payment_status');
            $table->index('invoice_status');
            $table->index('issued_at');
            $table->index('due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
