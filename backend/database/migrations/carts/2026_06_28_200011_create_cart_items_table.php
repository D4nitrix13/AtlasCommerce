<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "cart_items" que almacena los productos dentro de un
     * carrito. Se utiliza un "cart_line_key" para identificar cada línea
     * de forma única, en lugar de depender únicamente de product_id.
     *
     * Esto permite:
     * - Agregar el mismo producto con diferentes variantes en el carrito
     * - Manejar productos personalizados (custom_design_id) sin colisiones
     * - Evitar problemas de NULL en PostgreSQL con restricciones UNIQUE:
     *   En PostgreSQL, dos valores NULL no violan una restricción UNIQUE,
     *   por lo que usar solo [cart_id, product_id] no garantizaría
     *   unicidad cuando product_variant_id o custom_design_id son NULL.
     *   El cart_line_key soluciona esto generando un identificador único
     *   que encapsula toda la configuración de la línea.
     */
    public function up(): void
    {
        Schema::create(table: 'cart_items', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'cart_id')
                ->constrained(table: 'carts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Variante opcional: permite al mismo producto con distintas variantes
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Diseño personalizado opcional: para productos configurables
            $table->foreignId(column: 'custom_design_id')
                ->nullable()
                ->constrained(table: 'custom_designs')
                ->nullOnDelete();

            // Clave única de línea: identifica la combinación product+variant+design
            $table->string(column: 'cart_line_key', length: 64);

            $table->unsignedInteger('quantity')->default(value: 1);
            $table->decimal(column: 'unit_price', total: 12, places: 2);
            $table->decimal(column: 'subtotal', total: 12, places: 2);

            $table->timestamps();

            $table->index(columns: ['cart_id'], name: 'cart_items_cart_id_idx');
            $table->index(columns: ['product_id'], name: 'cart_items_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'cart_items_product_variant_id_idx');
            $table->unique(columns: ['cart_id', 'cart_line_key'], name: 'cart_items_cart_line_key_unique');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "cart_items" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'cart_items');
    }
};
