<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de métodos de pago para configurar
     * las opciones de pago disponibles en el store.
     */
    public function up(): void
    {
        Schema::create(table: 'payment_methods', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre del método de pago para identificación en el sistema y frontend
            $table->string(column: 'name', length: 100);
            // Descripción detallada del método de pago, nullable si no se requiere explicación
            $table->text(column: 'description')->nullable();
            // Estado de activación: permite ocultar métodos de pago sin eliminar su configuración
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de transacciones con métodos de pago eliminados
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'payment_methods_name_idx');
            $table->index(columns: ['is_active'], name: 'payment_methods_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'payment_methods');
    }
};
