<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_grouped_specs` para la Fase 8 (contenido
 * extendido).
 *
 * Modela el agrupador de specs por categoría para la PDP estilo
 * Gibson (Cuerpo, Mástil, Electrónica, Hardware, Miscelánea). Cada
 * producto tiene 0..N grupos; cada grupo tiene 1..N valores
 * (label/value) en la tabla hermana `product_spec_values`.
 *
 * Dos tablas porque una spec tiene un grupo (Cuerpo) y N valores
 * (Madera: Caoba, Tapa: Arce) — modelar esto con JSON en una sola
 * tabla forzaría queries con `->whereJsonContains` no indexadas y
 * rompería la posición por fila individual.
 *
 * FK `product_id` con `cascadeOnDelete`: borrar el producto borra
 * todos sus grupos (que a su vez borran sus valores por la FK
 * cascade de `product_spec_values.grouped_spec_id`).
 *
 * `position` define el orden de renderizado de los grupos en el
 * acordeón de specs (ascendente). `category` es el label visible
 * del grupo (ej. "Cuerpo", "Mástil").
 *
 * Índice compuesto `(product_id, position)` optimiza la query del
 * storefront: "dame los grupos del producto X ordenados".
 *
 * Defaults seguros: cualquier producto existente no tiene filas en
 * esta tabla (el acordeón no se renderiza).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_grouped_specs', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Label visible del grupo (ej. "Cuerpo", "Mástil").
            $table->string(column: 'category', length: 64);

            // Posición de renderizado del grupo en el acordeón (ascendente).
            $table->unsignedInteger(column: 'position')->default(value: 0);

            $table->timestamps();

            // Query canónica del storefront: grupos de un producto ordenados.
            $table->index(
                columns: ['product_id', 'position'],
                name: 'product_grouped_specs_product_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_grouped_specs');
    }
};
