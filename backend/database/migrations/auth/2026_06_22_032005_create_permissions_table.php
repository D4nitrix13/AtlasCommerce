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
     * Crea la tabla `permissions` que almacena los permisos del sistema.
     * Cada permiso tiene un nombre legible, un slug único para referencia
     * programática, y un módulo al que pertenece para agrupación lógica.
     */
    public function up(): void
    {
        Schema::create(table: 'permissions', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->string(column: 'name', length: 100);
            $table->string(column: 'slug', length: 120)->unique();
            $table->string(column: 'module', length: 80);
            // Descripción opcional, no todos los permisos requieren explicación detallada
            $table->text(column: 'description')->nullable();

            $table->timestamps();

            // Índice para búsquedas frecuentes por módulo (listados de permisos por módulo)
            $table->index(columns: ['module'], name: 'permissions_module_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'permissions');
    }
};
