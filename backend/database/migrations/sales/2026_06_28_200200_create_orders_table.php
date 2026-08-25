<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
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
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('sales_channel_id')
                ->nullable()
                ->constrained('sales_channels')
                ->nullOnDelete();
            $table->foreignId('user_creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->string('order_number', 80)->unique();

            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);

            $table->decimal('exchange_rate_snapshot', 18, 8);
            $table->unsignedBigInteger('total_in_base_currency_cents')->default(0);

            $table->string('status', 50)->default('pending');
            $table->timestamp('ordered_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status', 'ordered_at'], 'orders_store_status_date_idx');
            $table->index(['customer_id', 'status'], 'orders_customer_status_idx');
            $table->index('ordered_at', 'orders_ordered_at_idx');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('custom_design_id')
                ->nullable()
                ->constrained('custom_designs')
                ->nullOnDelete();

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('subtotal_cents');

            $table->string('product_name_snapshot', 160)->nullable();
            $table->string('product_sku_snapshot', 120)->nullable();

            $table->timestamps();

            $table->index(['order_id'], 'order_items_order_id_idx');
            $table->index(['product_id'], 'order_items_product_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
