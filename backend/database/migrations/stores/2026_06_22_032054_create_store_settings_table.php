<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'store_settings' que almacena la configuración general de la tienda
     * incluyendo nombre, logo, datos de contacto, dirección, moneda primaria y modo
     * mantenimiento. Esta tabla complementa la información de la tabla 'stores' con
     * ajustes adicionales de presentación y funcionamiento.
     */
    public function up(): void
    {
        Schema::create(table: 'store_settings', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->string(column: 'store_name', length: 160);
            $table->string(column: 'logo')->nullable();
            $table->string(column: 'email', length: 150)->nullable();
            $table->string(column: 'phone', length: 30)->nullable();
            $table->text(column: 'address')->nullable();
            // Moneda primaria para mostrar precios en la tienda
            $table->string(column: 'primary_currency', length: 10)->default(value: 'NIO');
            // Modo mantenimiento desactiva temporalmente la tienda para los clientes
            $table->boolean(column: 'maintenance_mode')->default(value: false);

            $table->timestamps();
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'store_settings' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_settings');
    }
};
