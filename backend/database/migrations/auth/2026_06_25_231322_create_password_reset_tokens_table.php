<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `password_reset_tokens` que gestiona los ciclos de vida
     * de tokens de restablecimiento de contraseña: generación, uso y expiración.
     */
    public function up(): void
    {
        Schema::create(table: 'password_reset_tokens', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Nullable porque el usuario puede solicitar reset sin estar autenticado
            // (solo necesita email). nullOnDelete para preservar tokens si se elimina el usuario.
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // index() para buscar tokens por email rápidamente
            $table->string(column: 'email')->index();
            // Hash del token, nunca se almacena el token en texto plano
            $table->string(column: 'token_hash');
            // index() en expires_at para limpiar tokens expirados
            $table->timestamp(column: 'expires_at')->index();
            // index() en used_at para identificar tokens ya utilizados
            $table->timestamp(column: 'used_at')->nullable()->index();

            // Metadatos de la petición para auditoría
            $table->string(column: 'request_ip', length: 45)->nullable();
            // User agent del navegador que solicitó el reset
            $table->text(column: 'user_agent')->nullable();

            $table->timestamps();

            // Índices compuestos optimizados para las consultas más frecuentes:
            // Buscar tokens válidos por email antes de expirar
            $table->index(columns: ['email', 'expires_at'], name: 'password_reset_email_expires_idx');
            // Consultar historial de resets por usuario
            $table->index(columns: ['user_id', 'used_at'], name: 'password_reset_user_used_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'password_reset_tokens');
    }
};
