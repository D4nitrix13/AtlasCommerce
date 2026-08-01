<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "wishlists" que almacena las listas de deseos de cada
     * cliente. Un cliente puede tener múltiples listas (ej: "Favoritos",
     * "Regalos de Navidad"), siendo una de ellas la predeterminada.
     */
    public function up(): void
    {
        Schema::create(table: 'wishlists', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tienda opcional: permite listas por tienda o listas globales
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            $table->string(column: 'name', length: 100)->default(value: 'Default Wishlist');
            $table->boolean(column: 'is_default')->default(value: true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'wishlists_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'wishlists_store_id_idx');
            $table->index(columns: ['is_default'], name: 'wishlists_is_default_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "wishlists" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'wishlists');
    }
};
