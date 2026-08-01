<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de relación entre productos y promociones
     * para gestionar qué productos participan en cada promoción.
     */
    public function up(): void
    {
        Schema::create(table: 'product_promotion', callback: function (Blueprint $table): void {
            $table->id();

            // Producto asociado a la promoción: cascadeOnDelete porque si se elimina el producto la relación no tiene sentido
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Promoción asociada al producto: cascadeOnDelete porque si se elimina la promoción la relación no tiene sentido
            $table->foreignId(column: 'promotion_id')
                ->constrained(table: 'promotions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            // Evita duplicados de producto-promoción
            $table->unique(columns: ['product_id', 'promotion_id'], name: 'product_promotion_product_id_promotion_id_unique');
            $table->index(columns: ['product_id'], name: 'product_promotion_product_id_idx');
            $table->index(columns: ['promotion_id'], name: 'product_promotion_promotion_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_promotion');
    }
};
