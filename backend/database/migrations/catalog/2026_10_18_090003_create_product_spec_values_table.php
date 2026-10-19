<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `product_spec_values` para la Fase 8 (contenido
 * extendido).
 *
 * Modela cada fila label/value dentro de un grupo de specs. La fila
 * pertenece a un `product_grouped_specs` (FK con cascade) y se
 * renderiza dentro del acordeón del grupo padre en la PDP estilo
 * Gibson (Cuerpo → Madera: Caoba, Tapa: Arce AAA).
 *
 * FK `grouped_spec_id` con `cascadeOnDelete`: borrar un grupo borra
 * todas sus filas (consistencia atómica — un grupo sin filas no tiene
 * sentido).
 *
 * `label` es el nombre del atributo (ej. "Madera", "Tapa"); `value`
 * es el valor legible (ej. "Caoba", "Arce AAA"). `text` para value
 * porque algunas specs pueden ser largas (ej. "Pastillas humbucker
 * Burstbucker Pro + Burstbucker Pro Plus").
 *
 * `position` define el orden de las filas dentro del grupo
 * (ascendente).
 *
 * Índice compuesto `(grouped_spec_id, position)` optimiza la query
 * del storefront: "dame los valores del grupo X ordenados".
 *
 * Defaults seguros: cualquier grupo existente sin filas simplemente
 * no muestra nada en el acordeón (la PDP lo saltea con length > 0).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'product_spec_values', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'grouped_spec_id')
                ->constrained(table: 'product_grouped_specs', column: 'id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Nombre del atributo (ej. "Madera", "Tapa", "Perfil").
            $table->string(column: 'label', length: 120);

            // Valor legible del atributo (puede ser largo, ej. "Burstbucker Pro + Burstbucker Pro Plus").
            $table->text(column: 'value');

            // Posición de la fila dentro del grupo (ascendente).
            $table->unsignedInteger(column: 'position')->default(value: 0);

            // Query canónica del storefront: valores de un grupo ordenados.
            $table->index(
                columns: ['grouped_spec_id', 'position'],
                name: 'product_spec_values_grouped_spec_position_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_spec_values');
    }
};
