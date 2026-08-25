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
     * Crea la tabla de relación entre productos e impuestos
     * para gestionar qué impuestos aplica a cada producto.
     */
    public function up(): void
    {
        Schema::create(table: 'product_tax', callback: function (Blueprint $table): void {
            $table->id();

            // Producto al que se le aplica el impuesto: cascadeOnDelete porque si se elimina el producto la relación no tiene sentido
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Impuesto aplicado al producto: cascadeOnDelete porque si se elimina el impuesto la relación no tiene sentido
            $table->foreignId(column: 'tax_id')
                ->constrained(table: 'taxes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            // Evita duplicados de producto-impuesto
            $table->unique(columns: ['product_id', 'tax_id'], name: 'product_tax_product_id_tax_id_unique');
            $table->index(columns: ['product_id'], name: 'product_tax_product_id_idx');
            $table->index(columns: ['tax_id'], name: 'product_tax_tax_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_tax');
    }
};
