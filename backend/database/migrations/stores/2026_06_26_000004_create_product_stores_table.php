<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'product_stores' que implementa la relación multi-tienda entre productos
     * y tiendas. Permite que un mismo producto tenga configuración diferente en cada tienda:
     * slug propio, precio público, descripción pública, visibilidad, destacado y orden de
     * clasificación. Se usan claves únicas compuestas para garantizar la integridad de datos.
     */
    public function up(): void
    {
        Schema::create(table: 'product_stores', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId(column: 'store_id')
                ->constrained()
                ->restrictOnDelete();

            // Slug propio del producto para esta tienda (permite URLs diferentes por tienda)
            $table->string(column: 'slug')->nullable();
            $table->string(column: 'public_title', length: 180)->nullable();
            // Precio público que puede diferir del precio base del producto
            $table->decimal(column: 'public_price', total: 12, places: 2)->nullable();
            // Descripción pública que puede diferir de la descripción base del producto
            $table->text(column: 'public_description')->nullable();

            // SEO por tienda
            $table->string(column: 'meta_title', length: 160)->nullable();
            $table->text(column: 'meta_description')->nullable();
            $table->string(column: 'og_image_url')->nullable();

            // Controla la visibilidad del producto en la tienda
            $table->boolean(column: 'is_visible')->default(value: true)->index();
            // Marca el producto como destacado en la tienda
            $table->boolean(column: 'is_featured')->default(value: false)->index();
            // Orden de clasificación para mostrar productos en la tienda
            $table->unsignedInteger(column: 'sort_order')->default(value: 0)->index();
            // Fecha de publicación del producto en la tienda
            $table->timestamp(column: 'published_at')->nullable()->index();
            $table->timestamp(column: 'unpublished_at')->nullable()->index();

            $table->timestamps();

            // Un producto solo puede aparecer una vez por tienda
            $table->unique(columns: ['product_id', 'store_id'], name: 'product_stores_product_store_unique');
            // Un slug solo puede existir una vez por tienda
            $table->unique(columns: ['store_id', 'slug'], name: 'product_stores_store_slug_unique');

            // Índice compuesto para filtrar productos visibles por tienda
            $table->index(columns: ['store_id', 'is_visible'], name: 'product_stores_store_visible_idx');
            // Índice compuesto para filtrar productos visibles y destacados por tienda
            $table->index(columns: ['store_id', 'is_visible', 'is_featured'], name: 'product_stores_visible_featured_idx');
            // Índice compuesto para ordenar productos por tienda
            $table->index(columns: ['store_id', 'sort_order'], name: 'product_stores_store_sort_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'product_stores' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_stores');
    }
};
