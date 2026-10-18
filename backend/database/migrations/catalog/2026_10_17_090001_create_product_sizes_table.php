<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_sizes` para la Fase 7 (schema rich de productos).
 *
 * Cada producto puede tener 0..N tallas opcionales. Ejemplos reales:
 *   - Violín 4/4, 3/4, 1/2 (instrumentos musicales de cuerda)
 *   - Ropa S/M/L/XL (vestimenta)
 *   - Cajas de regalo small/medium/large
 *
 * Para guitarras eléctricas (el caso dominante de La Voz Instrumentos)
 * las tallas NO aplican — `products.has_sizes` queda en `false` y la
 * PDP no renderiza este grid. La tabla existe para soportar tenants
 * que sí venden productos con tallas (vestimenta, calzado, joyería).
 *
 * El campo `slug` se guarda normalizado a URL-safe (ej. `4-4`, `3-4`,
 * `1-2`, `s`, `m`, `l`). El campo `label` es la versión legible para
 * la UI (ej. `4/4 (Full Size)`, `3/4`). `position` define el orden de
 * renderizado en el grid (ascendente).
 *
 * Combinación única `(product_id, slug)`: dos tallas con el mismo slug
 * dentro del mismo producto son imposibles (data integrity).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_sizes', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Slug URL-safe (ej. '4-4', 's', 'm', 'l').
            $table->string(column: 'slug', length: 64);

            // Label legible para UI (ej. '4/4 (Full Size)', 'Small').
            $table->string(column: 'label', length: 64);

            // Posición de renderizado en el grid de tallas.
            $table->unsignedInteger(column: 'position')->default(value: 0);

            $table->timestamps();

            // Un producto no puede tener dos tallas con el mismo slug.
            $table->unique(
                columns: ['product_id', 'slug'],
                name: 'product_sizes_product_slug_unique',
            );

            // Indice para query de "tallas de este producto ordenadas".
            $table->index(
                columns: ['product_id', 'position'],
                name: 'product_sizes_product_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_sizes');
    }
};
