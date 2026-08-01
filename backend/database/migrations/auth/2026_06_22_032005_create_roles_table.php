<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `roles` que define los roles del sistema (admin, user, etc.).
     * Cada rol tiene nombre y slug únicos, y puede activarse o desactivarse.
     * Se usa soft deletes para preservar integridad referencial con usuarios existentes.
     */
    public function up(): void
    {
        Schema::create(table: 'roles', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->string(column: 'name', length: 80)->unique();
            $table->string(column: 'slug', length: 100)->unique();
            // Descripción opcional para documentar el propósito del rol
            $table->text(column: 'description')->nullable();
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para no perder referencias en tablas dependientes al desactivar un rol
            $table->softDeletes();

            // Índice para filtrar roles activos en consultas frecuentes
            $table->index(columns: ['is_active'], name: 'roles_is_active_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'roles');
    }
};
