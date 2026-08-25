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
     * Crea la tabla de impuestos para configurar las tasas
     * impositivas aplicables a los productos del catálogo.
     */
    public function up(): void
    {
        Schema::create(table: 'taxes', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre del impuesto para identificación (ej: 'IVA 15%', 'ICE 10%')
            $table->string(column: 'name', length: 100);
            // Porcentaje del impuesto con 2 decimales para precisión (ej: 15.00, 10.50)
            $table->decimal(column: 'percentage', total: 5, places: 2);
            // Estado de activación: permite deshabilitar impuestos sin eliminar su configuración
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de impuestos aplicados a productos eliminados
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'taxes_name_idx');
            $table->index(columns: ['is_active'], name: 'taxes_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'taxes');
    }
};
