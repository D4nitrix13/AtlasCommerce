<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "product_variants" para gestionar las variantes de
     * un producto (ej: talla M en color rojo). Cada variante tiene su
     * propio SKU, precio y costo independientes del producto base,
     * permitiendo control granular de inventario y precios por variante.
     */
    public function up(): void
    {
        Schema::create(table: 'product_variants', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Eliminación en cascada: si se elimina el producto, se eliminan sus variantes
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // SKU único de la variante: identificador de inventario específico
            $table->string(column: 'variant_sku', length: 120)->unique();
            $table->string(column: 'variant_name', length: 160);
            $table->decimal(column: 'price', total: 12, places: 2);
            $table->decimal(column: 'cost', total: 12, places: 2)->default(value: 0);
            $table->string(column: 'barcode', length: 120)->nullable()->unique();
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['product_id'], name: 'product_variants_product_id_idx');
            $table->index(columns: ['variant_sku'], name: 'product_variants_variant_sku_idx');
            $table->index(columns: ['barcode'], name: 'product_variants_barcode_idx');
            $table->index(columns: ['is_active'], name: 'product_variants_is_active_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "product_variants" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_variants');
    }
};
