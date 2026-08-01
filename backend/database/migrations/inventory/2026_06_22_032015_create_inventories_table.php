<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de inventarios que vincula productos con almacenes
     * y registra los niveles de stock para cada combinación.
     */
    public function up(): void
    {
        Schema::create(table: 'inventories', callback: function (Blueprint $table): void {
            $table->id();

            // Relación con almacén: cada registro de inventario pertenece a un almacén
            $table->foreignId(column: 'warehouse_id')
                ->constrained(table: 'warehouses')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Relación con producto: se cascadeOnDelete porque si se elimina el producto no tiene sentido conservar su inventario
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Variante de producto nullable: permite inventario a nivel de producto base sin variante
            // nullOnDelete: si se elimina la variante, el inventario vuelve a nivel de producto base
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Stock actual disponible para venta
            $table->unsignedInteger(column: 'current_stock')->default(value: 0);
            // Nivel mínimo de stock para alertas de reabastecimiento
            $table->unsignedInteger(column: 'minimum_stock')->default(value: 0);
            // Stock reservado por pedidos pendientes, no disponible para nuevas ventas
            $table->unsignedInteger(column: 'reserved_stock')->default(value: 0);

            $table->timestamps();

            $table->index(columns: ['warehouse_id'], name: 'inventories_warehouse_id_idx');
            $table->index(columns: ['product_id'], name: 'inventories_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'inventories_product_variant_id_idx');
            $table->index(columns: ['current_stock'], name: 'inventories_current_stock_idx');
            $table->index(columns: ['minimum_stock'], name: 'inventories_minimum_stock_idx');
            $table->index(columns: ['reserved_stock'], name: 'inventories_reserved_stock_idx');

            // Índice único para evitar duplicados de producto+variante en el mismo almacén
            $table->unique(
                columns: ['warehouse_id', 'product_id', 'product_variant_id'],
                name: 'inventories_warehouse_product_variant_unique'
            );
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'inventories');
    }
};
