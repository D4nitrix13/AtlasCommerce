<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'invoice_items' que almacena los productos facturados de cada factura.
     * Cada ítem registra el producto, variante (opcional), cantidad, precio unitario y subtotal.
     * Se indexan las claves foráneas para optimizar las consultas de búsqueda por factura.
     */
    public function up(): void
    {
        Schema::create(table: 'invoice_items', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'invoice_id')
                ->constrained(table: 'invoices')
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

            $table->unsignedInteger('quantity');
            $table->decimal(column: 'unit_price', total: 12, places: 2);
            $table->decimal(column: 'subtotal', total: 12, places: 2);

            $table->timestamps();

            $table->index(columns: ['invoice_id'], name: 'invoice_items_invoice_id_idx');
            $table->index(columns: ['product_id'], name: 'invoice_items_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'invoice_items_product_variant_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'invoice_items' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'invoice_items');
    }
};
