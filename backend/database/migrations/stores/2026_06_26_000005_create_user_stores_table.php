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
     * Crea la tabla 'user_stores' que implementa la relación multi-tienda entre usuarios
     * y tiendas. Permite que un usuario tenga acceso a múltiples tiendas con un rol
     * específico en cada una. La combinación usuario-tienda es única para evitar duplicados.
     */
    public function up(): void
    {
        Schema::create(table: 'user_stores', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // El rol es opcional; si no se especifica, el usuario tiene permisos base
            $table->foreignId(column: 'role_id')
                ->nullable()
                ->constrained(table: 'roles')
                ->restrictOnDelete();

            $table->timestamps();

            // Un usuario solo puede tener una entrada por tienda
            $table->unique(columns: ['user_id', 'store_id'], name: 'user_stores_user_id_store_id_unique');
            $table->index(columns: ['store_id'], name: 'user_stores_store_id_idx');
            $table->index(columns: ['role_id'], name: 'user_stores_role_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'user_stores' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'user_stores');
    }
};
