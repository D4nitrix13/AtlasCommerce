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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('shipping_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->foreignId('sales_channel_id')
                ->nullable()
                ->constrained('sales_channels')
                ->nullOnDelete();

            $table->foreignId('user_creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('order_number', 80)->unique();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('status', 50)->default('pending');
            $table->timestamp('ordered_at')->useCurrent();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('shipping_address_id');
            $table->index('store_id');
            $table->index('sales_channel_id');
            $table->index('user_creator_id');
            $table->index('order_number');
            $table->index('status');
            $table->index('ordered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
