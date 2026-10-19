<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_extended_descriptions` para la Fase 8
 * (contenido extendido).
 *
 * Modela el bloque de descripción extendida que la PDP muestra
 * después de la descripción corta (la del modelo `Product`). Permite
 * soportar múltiples bloques temáticos (ej. "The Les Paul, llevada
 * al extremo" + bloque histórico) sin acoplarlos al campo `text`
 * largo de la tabla `products` (que se mantiene para el resumen
 * corto del card y del meta tag).
 *
 * `title` es el heading del bloque (ej. "The Les Paul, llevada al
 * extremo"). `body` es el texto completo del bloque (text porque
 * algunos bloques pueden ser párrafos largos).
 *
 * FK `product_id` con `cascadeOnDelete`: borrar el producto borra
 * todos sus bloques extendidos (consistencia atómica).
 *
 * `position` define el orden de los bloques en la sección (ascendente).
 *
 * Índice compuesto `(product_id, position)` optimiza la query del
 * storefront: "dame los bloques de descripción extendida del producto
 * X ordenados".
 *
 * Defaults seguros: cualquier producto existente no tiene filas (la
 * sección no se renderiza, el PDP sigue mostrando solo la descripción
 * corta de `products.description`).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_extended_descriptions', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Título del bloque (heading visible).
            $table->string(column: 'title', length: 200);

            // Cuerpo del bloque (puede ser párrafo largo).
            $table->text(column: 'body');

            // Posición del bloque dentro de la sección (ascendente).
            $table->unsignedInteger(column: 'position')->default(value: 0);

            $table->timestamps();

            // Query canónica del storefront: bloques de un producto ordenados.
            $table->index(
                columns: ['product_id', 'position'],
                name: 'product_extended_descriptions_product_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_extended_descriptions');
    }
};
