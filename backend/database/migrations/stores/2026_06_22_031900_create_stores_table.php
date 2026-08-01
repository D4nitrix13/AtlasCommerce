<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'stores' que es la tabla principal del sistema multi-tienda.
     * Cada tienda tiene su propia configuración de moneda, colores, datos de contacto
     * y estado de actividad/mantenimiento. El slug es único y se usa para identificar
     * la tienda en URLs amigables. Se permite soft deletes para preservar historial.
     */
    public function up(): void
    {
        Schema::create(table: 'stores', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // La moneda es opcional; se usa la moneda primaria por defecto si no se especifica
            $table->foreignId(column: 'currency_id')
                ->nullable()
                ->constrained(table: 'currencies')
                ->nullOnDelete();

            $table->string(column: 'name', length: 160);
            // Slug único para URLs amigables: /tienda/mi-tienda
            $table->string(column: 'slug', length: 160)->unique();
            $table->string(column: 'logo')->nullable();
            $table->string(column: 'email', length: 150)->nullable();
            $table->string(column: 'phone', length: 30)->nullable();
            $table->string(column: 'whatsapp', length: 30)->nullable();
            $table->text(column: 'address')->nullable();
            $table->text(column: 'description')->nullable();

            // Moneda primaria usada para mostrar precios en la tienda
            $table->string(column: 'primary_currency', length: 10)->default(value: 'NIO');
            $table->string(column: 'primary_color', length: 20)->nullable();
            $table->string(column: 'secondary_color', length: 20)->nullable();

            // Modo mantenimiento desactiva temporalmente la tienda para los clientes
            $table->boolean(column: 'maintenance_mode')->default(value: false)->index();
            // Controla si la tienda está visible y operativa
            $table->boolean(column: 'is_active')->default(value: true)->index();

            $table->timestamps();
            $table->softDeletes();

            // Índice compuesto para filtrar tiendas activas por moneda
            $table->index(columns: ['currency_id', 'is_active'], name: 'stores_currency_active_idx');
            // Índice compuesto para filtrar tiendas por estado de actividad y mantenimiento
            $table->index(columns: ['is_active', 'maintenance_mode'], name: 'stores_active_maintenance_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'stores' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'stores');
    }
};
