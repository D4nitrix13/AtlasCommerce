<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "product_images" para almacenar las imágenes de cada
     * producto. Permite múltiples imágenes por producto con orden de
     * visualización y una imagen principal marcada con is_primary.
     */
    public function up(): void
    {
        Schema::create(table: 'product_images', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Eliminación en cascada: si se elimina el producto, se eliminan sus imágenes
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string(column: 'image_url');
            $table->string(column: 'alt_text', length: 160)->nullable();
            $table->boolean(column: 'is_primary')->default(value: false);
            $table->unsignedInteger(column: 'sort_order')->default(value: 0);

            $table->timestamps();

            $table->index(columns: ['product_id'], name: 'product_images_product_id_idx');
            $table->index(columns: ['is_primary'], name: 'product_images_is_primary_idx');
            $table->index(columns: ['sort_order'], name: 'product_images_sort_order_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "product_images" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_images');
    }
};
