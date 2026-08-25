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
     * Crea la tabla de proveedores para gestionar las fuentes
     * de abastecimiento de productos del catálogo.
     */
    public function up(): void
    {
        Schema::create(table: 'suppliers', callback: function (Blueprint $table): void {
            $table->id();

            // Nombre comercial del proveedor para identificación en el sistema
            $table->string(column: 'name', length: 160);
            // RUC o identificación fiscal, nullable y único: permite proveedores sin identificación formal
            $table->string(column: 'ruc', length: 80)->nullable()->unique();
            // Teléfono de contacto principal del proveedor
            $table->string(column: 'phone', length: 30)->nullable();
            // Email de contacto, nullable y único para comunicación comercial
            $table->string(column: 'email', length: 150)->nullable()->unique();
            // Dirección física del proveedor, nullable si no se requiere dirección física
            $table->text(column: 'address')->nullable();
            // Estado de activación: permite deshabilitar proveedores sin eliminar su historial
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de compras realizadas a proveedores eliminados
            $table->softDeletes();

            $table->index(columns: ['name'], name: 'suppliers_name_idx');
            $table->index(columns: ['ruc'], name: 'suppliers_ruc_idx');
            $table->index(columns: ['phone'], name: 'suppliers_phone_idx');
            $table->index(columns: ['email'], name: 'suppliers_email_idx');
            $table->index(columns: ['is_active'], name: 'suppliers_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'suppliers');
    }
};
