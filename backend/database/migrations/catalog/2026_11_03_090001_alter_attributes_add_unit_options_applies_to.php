<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende `attributes` con unidad, opciones de enum y alcance.
 *
 * Los atributos del catálogo varían por tipo de producto: la taza se
 * define por su capacidad en onzas/libras, la camisa y la gorra por su
 * talla. Hasta acá el diccionario solo guardaba `type`, y no había forma
 * de decir "esto se mide en oz" ni de enumerar las opciones válidas de
 * un `select` (CH/M/G/XG) ni de acotar el atributo a las categorías donde
 * aplica.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'attributes', callback: function (Blueprint $table): void {
            // Unidad de medida ('oz', 'lb', 'ml', 'kg', 'cm'). NULL cuando
            // el atributo no se mide (p. ej. material, color).
            $table->string(column: 'unit', length: 16)->nullable()->after(column: 'type');

            // Opciones válidas cuando `type = 'select'` (JSON array).
            $table->jsonb(column: 'options')->nullable()->after(column: 'unit');

            // Categorías donde aplica el atributo (JSON array de slugs).
            // NULL = aplica a todas.
            $table->jsonb(column: 'applies_to')->nullable()->after(column: 'options');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'attributes', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['unit', 'options', 'applies_to']);
        });
    }
};
