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
     * Crea la tabla 'customer_stores' que implementa la relación multi-tienda entre clientes
     * y tiendas. Permite que un cliente esté registrado en múltiples tiendas. La combinación
     * cliente-tienda es única para evitar registros duplicados.
     */
    public function up(): void
    {
        Schema::create(table: 'customer_stores', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            // Un cliente solo puede tener una entrada por tienda
            $table->unique(columns: ['customer_id', 'store_id'], name: 'customer_stores_customer_id_store_id_unique');
            $table->index(columns: ['store_id'], name: 'customer_stores_store_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'customer_stores' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'customer_stores');
    }
};
