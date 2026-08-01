<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de monedas para configurar las opciones
     * de moneda disponibles para precios y pagos.
     */
    public function up(): void
    {
        Schema::create(table: 'currencies', callback: function (Blueprint $table): void {
            $table->id();

            // Código ISO de la moneda (ej: 'USD', 'EUR', 'PEN'), único para evitar duplicados
            $table->string(column: 'code', length: 10)->unique();
            // Nombre completo de la moneda para visualización
            $table->string(column: 'name', length: 80);
            // Símbolo de la moneda para mostrar precios (ej: '$', '€', 'S/')
            $table->string(column: 'symbol', length: 10);
            // Indicador de moneda primaria del sistema para cálculos por defecto
            $table->boolean(column: 'is_primary')->default(value: false);
            // Estado de activación: permite ocultar monedas sin eliminar su configuración
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();

            $table->index(columns: ['code'], name: 'currencies_code_idx');
            $table->index(columns: ['is_primary'], name: 'currencies_is_primary_idx');
            $table->index(columns: ['is_active'], name: 'currencies_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'currencies');
    }
};
