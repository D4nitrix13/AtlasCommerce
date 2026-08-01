<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();
            $table->string('status', 50)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status'], 'carts_customer_status_idx');
            $table->index(['store_id', 'status'], 'carts_store_status_idx');
            $table->index('expires_at', 'carts_expires_at_idx');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained('carts')
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

            $table->string('cart_line_key', 64);

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('subtotal_cents');

            $table->timestamps();

            $table->index(['cart_id', 'cart_line_key'], 'cart_items_cart_line_key_unique');
            $table->index('product_id', 'cart_items_product_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
