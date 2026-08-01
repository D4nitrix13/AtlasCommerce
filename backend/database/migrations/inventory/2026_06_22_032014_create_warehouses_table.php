<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de almacenes para gestionar las ubicaciones
     * de almacenamiento de productos del inventario.
     */
    public function up(): void
    {
        Schema::create(table: 'warehouses', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre único del almacén para identificación interna
            $table->string(column: 'name', length: 120);
            // Dirección física completa del almacén, nullable porque puede ser una bodega sin dirección formal
            $table->text(column: 'address')->nullable();
            // Teléfono de contacto del almacén, nullable si no se requiere contacto telefónico
            $table->string(column: 'phone', length: 30)->nullable();
            // Estado de activación: permite ocultar almacenes sin eliminarlos del sistema
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de almacenes eliminados
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'warehouses_name_idx');
            $table->index(columns: ['phone'], name: 'warehouses_phone_idx');
            $table->index(columns: ['is_active'], name: 'warehouses_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'warehouses');
    }
};
