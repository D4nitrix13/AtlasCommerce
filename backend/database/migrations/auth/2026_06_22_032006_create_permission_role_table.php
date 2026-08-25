<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla pivote `permission_role` que relaciona roles con permisos
     * en una relación N:N. Permite asignar múltiples permisos a cada rol y
     * que un permiso pertenezca a múltiples roles.
     */
    public function up(): void
    {
        Schema::create(table: 'permission_role', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cascade en ambas direcciones: si se elimina un rol o permiso,
            // se eliminan las relaciones asociadas para mantener integridad
            $table->foreignId(column: 'role_id')
                ->constrained(table: 'roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'permission_id')
                ->constrained(table: 'permissions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            // unique() para evitar duplicados de la misma relación rol-permiso
            $table->unique(columns: ['role_id', 'permission_id'], name: 'permission_role_role_id_permission_id_unique');
            // Índices individuales para consultas que filtran por un solo lado de la relación
            $table->index(columns: ['role_id'], name: 'permission_role_role_id_idx');
            $table->index(columns: ['permission_id'], name: 'permission_role_permission_id_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'permission_role');
    }
};
