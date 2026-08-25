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
     * Imágenes específicas de un producto dentro de una tienda. Permite que cada
     * tienda tenga sus propias fotos, galerías y textos alternativos para el mismo producto.
     */
    public function up(): void
    {
        Schema::create(table: 'product_store_images', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // FK: imagen vinculada a la relación producto-tienda
            $table->foreignId(column: 'product_store_id')
                ->constrained(table: 'product_stores')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'image_url');
            $table->string(column: 'alt_text', length: 255)->nullable();
            $table->boolean(column: 'is_primary')->default(value: false);
            $table->unsignedInteger(column: 'sort_order')->default(value: 0);

            $table->timestamps();

            $table->index(columns: ['product_store_id'], name: 'psi_product_store_id_idx');
            $table->index(columns: ['product_store_id', 'is_primary'], name: 'psi_store_primary_idx');
            $table->index(columns: ['product_store_id', 'sort_order'], name: 'psi_store_sort_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_store_images');
    }
};
