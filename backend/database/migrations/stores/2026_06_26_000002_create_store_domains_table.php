<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Permite asociar múltiples dominios/rutas a cada tienda para resolución
     * por dominio, subdominio o path. Soporta redirect y verificación.
     */
    public function up(): void
    {
        Schema::create(table: 'store_domains', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'domain', length: 255);
            $table->string(column: 'type', length: 20)->default(value: 'domain');
            $table->string(column: 'base_path', length: 255)->default(value: '/');
            $table->boolean(column: 'redirect_to_primary')->default(value: false);
            // Marca el dominio principal de la tienda
            $table->boolean(column: 'is_primary')->default(value: false);
            $table->boolean(column: 'is_active')->default(value: true);
            $table->timestamp(column: 'verified_at')->nullable();

            $table->timestamps();

            // Combinación única: un dominio+path no puede repetirse
            $table->unique(columns: ['domain', 'base_path'], name: 'store_domains_domain_path_unique');

            $table->index(columns: ['store_id'], name: 'store_domains_store_id_idx');
            $table->index(columns: ['domain'], name: 'store_domains_domain_idx');
            $table->index(columns: ['type'], name: 'store_domains_type_idx');
            $table->index(columns: ['is_primary'], name: 'store_domains_is_primary_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_domains');
    }
};
