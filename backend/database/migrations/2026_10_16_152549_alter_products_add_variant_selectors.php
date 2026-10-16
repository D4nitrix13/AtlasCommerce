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
     * Agrega flags de selectores de variantes al producto:
     *   - `has_condition`: muestra el selector "Nuevo / Usado" en la PDP.
     *     Default true porque la mayoría de los productos de e-commerce
     *     pueden venderse nuevos o usados (ropa vintage, autos, antigüedades).
     *     Para La Voz Instrumentos los instrumentos son nuevos por default
     *     pero el flag sigue siendo true para coherencia con el catálogo.
     *   - `has_sizes`: muestra el grid de tallas en la PDP (S/M/L, 4/4/3/4).
     *     Default false porque la mayoría de los productos no tienen tallas
     *     relevantes (instrumentos musicales, electrónica, autos). Los
     *     tenants que venden ropa deben setearlo a true por producto.
     *
     * Los defaults son seguros: cualquier producto existente (incluidos
     * los del seeder `FenderPlayerFusionBassSeeder`) recibe estos flags
     * automáticamente al correr la migración. No rompe reseñas, ni
     * variantes, ni imágenes pre-existentes.
     *
     * Productos que SÍ deben mostrarlos los configuran explícitamente
     * en su seeder o vía admin.
     */
    public function up(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table): void {
            $table->boolean(column: 'has_condition')->default(value: true)->after(column: 'is_active');
            $table->boolean(column: 'has_sizes')->default(value: false)->after(column: 'has_condition');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['has_condition', 'has_sizes']);
        });
    }
};
