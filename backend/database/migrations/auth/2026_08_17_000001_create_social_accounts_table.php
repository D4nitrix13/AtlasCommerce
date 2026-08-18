<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `social_accounts`.
     *
     * Almacena el binding entre una identidad externa (Google/Facebook)
     * y un usuario interno. Una fila por par `(provider, provider_user_id)`
     * — la unicidad la enforce la base vía índice único compuesto, no la
     * capa de aplicación (R12 del proposal).
     *
     * Decisiones:
     *   - `provider` VARCHAR(16): alcanza para 'google' y 'facebook'.
     *     Si el día de mañana entra Apple/X/etc. se queda corto y se hace
     *     un alter.
     *   - `provider_user_id` VARCHAR(128): holgura para IDs de provider
     *     (Google usa 21 chars numéricos; Facebook usa 15-25; los dejamos
     *     espacio para crecer).
     *   - `cascadeOnDelete`: si el user se borra, sus bindings sociales
     *     se van con él. Razón: un `social_account` huérfano no tiene
     *     razón de existir.
     */
    public function up(): void
    {
        Schema::create(table: 'social_accounts', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            $table->string(column: 'provider', length: 16);
            $table->string(column: 'provider_user_id', length: 128);

            $table->timestamps();

            // Índice único compuesto: una sola fila por par provider + id externo.
            $table->unique(columns: ['provider', 'provider_user_id'], name: 'social_accounts_provider_provider_user_id_unique');
            // Índice simple: filtros por user_id (ej. "listar providers del user").
            $table->index(columns: ['user_id'], name: 'social_accounts_user_id_idx');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'social_accounts');
    }
};
