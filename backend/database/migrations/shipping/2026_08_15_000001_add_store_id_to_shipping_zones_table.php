<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     *
     * Agrega `store_id` (FK nullable) a `shipping_zones` para
     * soportar aislamiento multi-tenant. Nullable porque:
     *  1. No requiere data-fill inmediato de filas existentes.
     *  2. Las filas NULL se interpretan como "globales" (visibles
     *     para todos los tenants) una vez que el trait
     *     `BelongsToStore` se adopte.
     */
    public function up(): void
    {
        Schema::table(table: 'shipping_zones', callback: function (Blueprint $table): void {
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->after(column: 'id')
                ->constrained(table: 'stores')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Índice compuesto para queries con filtro por store + active.
            $table->index(
                columns: ['store_id', 'is_active'],
                name: 'shipping_zones_store_active_idx',
            );
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'shipping_zones', callback: function (Blueprint $table): void {
            $table->dropIndex('shipping_zones_store_active_idx');
            $table->dropForeign(['store_id']);
            $table->dropColumn(columns: ['store_id']);
        });
    }
};
