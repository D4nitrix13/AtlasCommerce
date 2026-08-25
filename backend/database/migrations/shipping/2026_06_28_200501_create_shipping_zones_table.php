<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de zonas de envío para configurar
     * las tarifas y coberturas geográficas de entrega.
     */
    public function up(): void
    {
        Schema::create(table: 'shipping_zones', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre de la zona para identificación (ej: 'Zona Centro', 'Zona Internacional')
            $table->string(column: 'name', length: 160);
            // Descripción detallada de la zona geográfica que cubre
            $table->text(column: 'description')->nullable();
            // Costo base del envío para esta zona, se suma a costos por peso/distancia
            $table->decimal(column: 'base_cost', total: 12, places: 2)->default(value: 0);
            // Estado de activación: permite pausar zonas de envío sin eliminar su configuración
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de envíos realizados a zonas eliminadas
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'shipping_zones_name_idx');
            $table->index(columns: ['is_active'], name: 'shipping_zones_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'shipping_zones');
    }
};
