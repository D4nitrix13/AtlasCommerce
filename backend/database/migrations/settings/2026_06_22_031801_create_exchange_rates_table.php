<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de tipos de cambio para registrar
     * los valores históricos de conversión entre monedas.
     */
    public function up(): void
    {
        Schema::create(table: 'exchange_rates', callback: function (Blueprint $table): void {
            $table->id();

            // Moneda a la que pertenece el tipo de cambio: cascadeOnDelete porque si se elimina la moneda sus tipos de cambio no tienen sentido
            $table->foreignId(column: 'currency_id')
                ->constrained(table: 'currencies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Valor del tipo de cambio con 6 decimales para alta precisión en conversiones
            $table->decimal(column: 'value', total: 12, places: 6);
            // Fecha del tipo de cambio para mantener un historial de variaciones
            $table->date(column: 'rate_date');

            $table->timestamps();

            // Evita duplicados de tipo de cambio por moneda y fecha
            $table->unique(columns: ['currency_id', 'rate_date'], name: 'exchange_rates_currency_id_rate_date_unique');
            $table->index(columns: ['currency_id'], name: 'exchange_rates_currency_id_idx');
            $table->index(columns: ['rate_date'], name: 'exchange_rates_rate_date_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'exchange_rates');
    }
};
