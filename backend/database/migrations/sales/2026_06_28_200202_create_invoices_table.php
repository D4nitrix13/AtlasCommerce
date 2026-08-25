<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
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
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('sales_channel_id')
                ->nullable()
                ->constrained('sales_channels')
                ->nullOnDelete();
            $table->foreignId('user_issuer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->string('invoice_number', 80)->unique();

            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);

            $table->decimal('exchange_rate_snapshot', 18, 8);

            $table->string('payment_status', 50)->default('pending');
            $table->string('invoice_status', 50)->default('issued');

            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('due_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'payment_status', 'issued_at'], 'invoices_store_payment_issued_idx');
            $table->index('order_id', 'invoices_order_id_idx');
            $table->index('customer_id', 'invoices_customer_id_idx');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
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

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('subtotal_cents');

            $table->string('product_name_snapshot', 160)->nullable();
            $table->string('product_sku_snapshot', 120)->nullable();

            $table->timestamps();

            $table->index('invoice_id', 'invoice_items_invoice_id_idx');
            $table->index('product_id', 'invoice_items_product_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
