<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade valor numérico y unidad a `product_sizes`.
 *
 * La talla/capacidad vive en la variante (opción A confirmada): una taza
 * de 11 oz y una de 15 oz son variantes comprables distintas. La tabla
 * `product_sizes` solo guardaba `slug`/`label`/`position`, así que "11 oz"
 * era texto plano y no se podía ordenar ni filtrar por capacidad.
 *
 * `value` + `unit` dejan expresar lo numérico (11 / 'oz') mientras que
 * las tallas de ropa (CH/M/G/XG) quedan solo con `slug`/`label`, sin
 * valor numérico — el caso de uso es distinto y no lo fuerza.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'product_sizes', callback: function (Blueprint $table): void {
            // 11 → 11 oz; 500 → 500 ml. NULL para tallas de ropa.
            $table->decimal(column: 'value', total: 10, places: 2)->nullable()->after(column: 'label');

            // 'oz', 'lb', 'ml', 'kg', 'cm'. NULL cuando `value` es NULL.
            $table->string(column: 'unit', length: 16)->nullable()->after(column: 'value');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'product_sizes', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['value', 'unit']);
        });
    }
};
