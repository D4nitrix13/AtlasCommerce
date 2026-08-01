<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de empresas de envío para configurar
     * las opciones de entrega disponibles en el store.
     */
    public function up(): void
    {
        Schema::create(table: 'shipping_companies', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre de la empresa de envío para identificación en el sistema
            $table->string(column: 'name', length: 160);
            // Teléfono de contacto de la empresa, nullable si no se requiere contacto telefónico
            $table->string(column: 'phone', length: 30)->nullable();
            // Email de contacto de la empresa, nullable para empresas sin contacto por email
            $table->string(column: 'email', length: 150)->nullable();
            // Estado de activación: permite ocultar empresas de envío sin eliminar su configuración
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de envíos realizados con empresas eliminadas
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'shipping_companies_name_idx');
            $table->index(columns: ['is_active'], name: 'shipping_companies_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'shipping_companies');
    }
};
