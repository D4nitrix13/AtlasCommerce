<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "wishlist_items" que almacena los productos guardados
     * en cada lista de deseos. Cada producto solo puede existir una vez
     * por lista, garantizado por la restricción UNIQUE compuesta.
     */
    public function up(): void
    {
        Schema::create(table: 'wishlist_items', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'wishlist_id')
                ->constrained(table: 'wishlists')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Fecha en que se agregó el producto a la lista
            $table->timestamp(column: 'added_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['wishlist_id'], name: 'wishlist_items_wishlist_id_idx');
            $table->index(columns: ['product_id'], name: 'wishlist_items_product_id_idx');
            $table->index(columns: ['added_at'], name: 'wishlist_items_added_at_idx');

            // Un producto solo puede estar una vez por lista de deseos
            $table->unique(
                columns: ['wishlist_id', 'product_id'],
                name: 'wishlist_items_wishlist_product_unique'
            );
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "wishlist_items" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'wishlist_items');
    }
};
