<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores tipados en `attribute_product`.
 *
 * `value` (VARCHAR) alcanza para "Algodón" o "Rojo", pero no para
 * filtrar u ordenar por un número ("peso ≤ 0.5 kg") ni para guardar un
 * booleano ("apto para microondas"). Las columnas tipadas conviven con
 * `value`: el texto sigue siendo la forma canónica de mostrar el dato y
 * las columnas nuevas habilitan consultas.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'attribute_product', callback: function (Blueprint $table): void {
            // 0.35 (kg), 11 (oz). NULL cuando el valor es texto.
            $table->decimal(column: 'value_number', total: 12, places: 4)->nullable()->after(column: 'value');

            // true/false. NULL cuando el valor no es booleano.
            $table->boolean(column: 'value_boolean')->nullable()->after(column: 'value_number');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'attribute_product', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['value_number', 'value_boolean']);
        });
    }
};
