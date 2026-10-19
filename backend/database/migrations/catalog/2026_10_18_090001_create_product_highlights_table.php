<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_highlights` para la Fase 8 (contenido extendido).
 *
 * Cada producto puede tener 0..N highlights (características destacadas
 * con icono + label, renderizadas en la PDP como grid visual estilo
 * Gibson). El icono es un slug URL-safe (`premio`, `destello`,
 * `regla`, `escudo`, `estrella`, `musica`) que mapea a un SVG inline
 * en `ProductHighlights.astro`. `label` es el texto legible que se
 * muestra bajo el icono (ej. "Cuerpo de caoba").
 *
 * La FK `product_id` usa `cascadeOnDelete`: borrar el producto borra
 * automáticamente sus highlights (consistencia atómica).
 *
 * `position` define el orden de renderizado en el grid (ascendente);
 * default `0` para que un producto existente con un solo highlight
 * funcione sin tener que especificar posición.
 *
 * El índice compuesto `(product_id, position)` optimiza la query
 * canónica del PDP: "dame los highlights del producto X ordenados".
 *
 * Defaults seguros: cualquier producto existente simplemente no tiene
 * filas en esta tabla (array vacío en el resource).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_highlights', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Slug del icono SVG (premio | destello | regla | escudo | estrella | musica).
            $table->string(column: 'icon', length: 32);

            // Texto legible bajo el icono (ej. "Cuerpo de caoba").
            $table->string(column: 'label', length: 200);

            // Posición de renderizado en el grid de la PDP (ascendente).
            $table->unsignedInteger(column: 'position')->default(value: 0);

            $table->timestamps();

            // Query canónica del storefront: highlights de un producto ordenados.
            $table->index(
                columns: ['product_id', 'position'],
                name: 'product_highlights_product_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_highlights');
    }
};
