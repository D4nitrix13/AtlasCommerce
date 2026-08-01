<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'store_domains' que permite asociar múltiples dominios personalizados
     * a cada tienda. Cada dominio tiene un flag de primario (el dominio principal de la tienda)
     * y un estado de actividad. El dominio es único en todo el sistema para evitar duplicados.
     */
    public function up(): void
    {
        Schema::create(table: 'store_domains', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Dominio único en todo el sistema para evitar conflictos entre tiendas
            $table->string(column: 'domain', length: 255)->unique();
            // Marca el dominio principal de la tienda
            $table->boolean(column: 'is_primary')->default(value: false);
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();

            $table->index(columns: ['store_id'], name: 'store_domains_store_id_idx');
            $table->index(columns: ['domain'], name: 'store_domains_domain_idx');
            $table->index(columns: ['is_primary'], name: 'store_domains_is_primary_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'store_domains' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_domains');
    }
};
