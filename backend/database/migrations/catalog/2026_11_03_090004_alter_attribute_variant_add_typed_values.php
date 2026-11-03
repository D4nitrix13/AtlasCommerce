<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores tipados en `attribute_variant`.
 *
 * Mismo motivo que en `attribute_product`: la variante puede llevar
 * datos numéricos o booleanos propios (capacidad, peso) que conviene
 * poder comparar en consultas, además del `value` textual que ya existe.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'attribute_variant', callback: function (Blueprint $table): void {
            // 11 (oz), 0.35 (kg). NULL cuando el valor es texto.
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
        Schema::table(table: 'attribute_variant', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['value_number', 'value_boolean']);
        });
    }
};
