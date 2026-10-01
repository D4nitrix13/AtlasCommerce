<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Items del bulk-cart del cliente.
     *
     * Bulk-cart es un carrito "industrial" usado para pedidos grandes
     * (B2B / mayorista) que se procesan vía RFQ en lugar del checkout
     * tradicional. Cada item referencia un product y quantity solicitada.
     */
    public function up(): void
    {
        Schema::create(table: 'bulk_cart_items', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->integer(column: 'quantity')->unsigned();
            $table->string(column: 'notes', length: 500)->nullable();

            $table->timestamps();

            $table->index(columns: ['customer_id'], name: 'bulk_cart_items_customer_idx');
            $table->index(columns: ['product_id'], name: 'bulk_cart_items_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'bulk_cart_items');
    }
};
