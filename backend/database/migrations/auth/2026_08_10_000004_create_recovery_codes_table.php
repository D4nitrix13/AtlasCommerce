<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `recovery_codes` que guarda los códigos de
     * recuperación de un solo uso. Cada usuario con 2FA habilitada
     * o que usa recovery codes como método exclusivo tiene 8-10
     * códigos hasheados.
     *
     * - `code_hash`: bcrypt del código alfanumérico (ej. 'a3f8b91c').
     *   El código plain-text SOLO se muestra al user UNA vez, en el
       momento de generación.
     * - `used_at`: cuando se consumió. null = todavía disponible.
     */
    public function up(): void
    {
        Schema::create(table: 'recovery_codes', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // FK a users. cascadeOnDelete: si se borra el user, se
            // borran sus codes.
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // Bcrypt hash del código. 255 chars cubre bcrypt (60) +
            // overhead de storage.
            $table->string(column: 'code_hash', length: 255);

            // Cuándo se consumió el code. null = todavía disponible.
            $table->timestamp(column: 'used_at')->nullable();

            $table->timestamps();

            // Índice compuesto para contar/buscar codes disponibles
            // (no usados) de un user. Usado por el dashboard de 2FA.
            $table->index(
                columns: ['user_id', 'used_at'],
                name: 'recovery_codes_user_unused_idx',
            );
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'recovery_codes');
    }
};
