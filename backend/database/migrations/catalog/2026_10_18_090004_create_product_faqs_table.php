<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_faqs` para la Fase 8 (contenido extendido).
 *
 * Cada producto puede tener 0..N preguntas frecuentes. La PDP las
 * renderiza en una sección dedicada (estilo Gibson) reusando el
 * componente genérico `FaqAccordion.astro` que ya existía para el
 * B2B Panda.
 *
 * `question` es el heading del acordeón (max 300 chars para
 * preguntas razonablemente largas pero acotadas). `answer` es el
 * cuerpo (text porque algunas respuestas pueden ser largas o tener
 * múltiples párrafos).
 *
 * FK `product_id` con `cascadeOnDelete`: borrar el producto borra
 * sus FAQs (consistencia atómica).
 *
 * `position` define el orden de las FAQs en la sección (ascendente).
 *
 * Índice compuesto `(product_id, position)` optimiza la query del
 * storefront: "dame las FAQs del producto X ordenadas".
 *
 * Defaults seguros: cualquier producto existente simplemente no tiene
 * filas (la sección FAQ no se renderiza cuando el array es vacío).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_faqs', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Pregunta visible en el summary del acordeón (heading).
            $table->string(column: 'question', length: 300);

            // Respuesta visible al expandir el acordeón (cuerpo).
            $table->text(column: 'answer');

            // Posición de la FAQ dentro de la sección (ascendente).
            $table->unsignedInteger(column: 'position')->default(value: 0);

            $table->timestamps();

            // Query canónica del storefront: FAQs de un producto ordenadas.
            $table->index(
                columns: ['product_id', 'position'],
                name: 'product_faqs_product_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_faqs');
    }
};
