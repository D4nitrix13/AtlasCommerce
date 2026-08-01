<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de ítems de compra para desglosar
     * los productos incluidos en cada orden de compra.
     */
    public function up(): void
    {
        Schema::create(table: 'purchase_items', callback: function (Blueprint $table): void {
            $table->id();

            // Cada ítem pertenece a una compra: cascadeOnDelete porque si se elimina la compra sus ítems no tienen sentido
            $table->foreignId(column: 'purchase_id')
                ->constrained(table: 'purchases')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Producto comprado: restrictOnDelete porque no se debe eliminar un producto que tenga compras asociadas
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Variante de producto nullable: permite compras a nivel de producto base sin variante específica
            // nullOnDelete: si se elimina la variante, el ítem de compra se mantiene vinculado al producto base
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Cantidad de unidades compradas de este ítem
            $table->unsignedInteger(column: 'quantity');
            // Precio unitario de compra acordado con el proveedor
            $table->decimal(column: 'purchase_price', total: 12, places: 2);
            // Subtotal del ítem (quantity * purchase_price)
            $table->decimal(column: 'subtotal', total: 12, places: 2);

            $table->timestamps();

            $table->index(columns: ['purchase_id'], name: 'purchase_items_purchase_id_idx');
            $table->index(columns: ['product_id'], name: 'purchase_items_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'purchase_items_product_variant_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'purchase_items');
    }
};
