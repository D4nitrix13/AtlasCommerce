<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "carts" que almacena los carritos de compra de cada
     * cliente. Un cliente puede tener múltiples carritos (por tienda) y
     * cada carrito tiene un estado que controla su ciclo de vida.
     */
    public function up(): void
    {
        Schema::create(table: 'carts', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Relación con el cliente propietario del carrito
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Tienda opcional: permite carritos globales o por tienda específica
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            $table->string(column: 'status', length: 50)->default(value: 'active');
            $table->timestamp(column: 'expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'carts_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'carts_store_id_idx');
            $table->index(columns: ['status'], name: 'carts_status_idx');
            $table->index(columns: ['expires_at'], name: 'carts_expires_at_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "carts" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'carts');
    }
};
