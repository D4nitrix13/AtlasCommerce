<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "products" que almacena el catálogo de productos.
     * Cada producto tiene un SKU único para identificación interna,
     * precios (base y costo) con precisión decimal, y relaciones
     * con categoría y marca. Los productos pueden tener variantes
     * (talla, color) que se gestionan en product_variants.
     */
    public function up(): void
    {
        Schema::create(table: 'products', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Categoría obligatoria: todo producto pertenece a una categoría
            $table->foreignId(column: 'category_id')
                ->constrained(table: 'categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Marca opcional: no todos los productos tienen marca
            $table->foreignId(column: 'brand_id')
                ->nullable()
                ->constrained(table: 'brands')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'name', length: 160);
            $table->string(column: 'slug', length: 180)->unique();
            $table->longText(column: 'description')->nullable();

            // SKU: identificador único de inventario para el producto base
            $table->string(column: 'sku', length: 120)->unique();
            $table->decimal(column: 'base_price', total: 12, places: 2);
            $table->decimal(column: 'cost', total: 12, places: 2)->default(value: 0);

            $table->boolean(column: 'is_active')->default(value: true);
            $table->timestamp(column: 'published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['category_id'], name: 'products_category_id_idx');
            $table->index(columns: ['brand_id'], name: 'products_brand_id_idx');
            $table->index(columns: ['sku'], name: 'products_sku_idx');
            $table->index(columns: ['slug'], name: 'products_slug_idx');
            $table->index(columns: ['is_active'], name: 'products_is_active_idx');
            $table->index(columns: ['published_at'], name: 'products_published_at_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "products" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'products');
    }
};
