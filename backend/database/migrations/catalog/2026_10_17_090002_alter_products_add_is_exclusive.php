<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el flag `is_exclusive` a la tabla `products` para la Fase 7
 * (schema rich de productos).
 *
 * `is_exclusive` indica que el producto es una "Edición Limitada" o
 * stock reducido intencionalmente (curated selection, drops, rare
 * finds). La PDP lo muestra como badge "Limitado" sobre la imagen
 * principal y en el catalog grid.
 *
 * Default `false`: cualquier producto existente (incluidos los del
 * `ProductSeeder` JSON-driven con 16 guitarras seedeadas) NO se
 * consideran exclusivos automáticamente. Los productos exclusivos
 * se configuran explícitamente en su seeder o vía admin.
 *
 * Ubicación física de la columna: queda entre `is_active` y
 * `has_condition` (orden físico de las columnas existentes al
 * momento de correr esta migración).
 *
 * No afecta: reseñas, variantes, imágenes, ni índices existentes.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table): void {
            $table->boolean(column: 'is_exclusive')
                ->default(value: false)
                ->after(column: 'is_active');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'products', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['is_exclusive']);
        });
    }
};
