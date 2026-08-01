<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'order_items' que almacena los productos asociados a cada orden.
     * Cada ítem registra el producto, variante (opcional), diseño personalizado (opcional),
     * cantidad, precio unitario, descuento y subtotal calculado.
     * Se indexan las claves foráneas para optimizar las consultas de búsqueda por orden.
     */
    public function up(): void
    {
        Schema::create(table: 'order_items', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'order_id')
                ->constrained(table: 'orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // La variante es opcional para productos que no tienen variantes
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // El diseño personalizado es opcional, solo aplica para productos con personalización
            $table->foreignId(column: 'custom_design_id')
                ->nullable()
                ->constrained(table: 'custom_designs')
                ->nullOnDelete();

            $table->unsignedInteger(column: 'quantity');
            $table->decimal(column: 'unit_price', total: 12, places: 2);
            $table->decimal(column: 'discount', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'subtotal', total: 12, places: 2);

            $table->timestamps();

            $table->index(columns: ['order_id'], name: 'order_items_order_id_idx');
            $table->index(columns: ['product_id'], name: 'order_items_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'order_items_product_variant_id_idx');
            // Índice compuesto para buscar ítems de una orden que tienen diseño personalizado
            $table->index(columns: ['order_id', 'custom_design_id'], name: 'order_items_order_design_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'order_items' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'order_items');
    }
};
