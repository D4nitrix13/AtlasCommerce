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
     * Crea la tabla `otp_challenges` que guarda challenges activos de
     * one-time-password. Se usa para:
     * - TOTP challenges (cada vez que el user hace login con 2FA)
     * - Email OTP challenges (recovery de contraseña, ya existe
     *   `password_reset_tokens` pero este es genérico)
     * - Recovery codes consumidos (audit, aunque el code ya está
     *   hasheado en `recovery_codes`)
     *
     * Cada challenge es de un solo uso (consumed_at), tiene TTL
     * (expires_at) y tracking de attempts (rate limiting).
     */
    public function up(): void
    {
        Schema::create(table: 'otp_challenges', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // FK a users. cascadeOnDelete: si se borra el user, se
            // borran sus challenges activos.
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // Tipo de challenge: 'totp' (2FA en login), 'email_otp'
            // (recovery de contraseña), 'recovery_code' (audit de
            // recovery codes consumidos).
            $table->string(column: 'type', length: 30);

            // Hash bcrypt del challenge. Nunca se guarda el code en
            // texto plano. Para TOTP el "code" es el codigo de 6
            // digitos hasheado. Para email es el codigo OTP de 6
            // digitos hasheado. Para recovery codes es el codigo
            // alfanumerico hasheado.
            $table->string(column: 'code_hash', length: 255);

            // Contexto opcional del challenge en JSON. Para password
            // reset guarda el email + ttl + flags. Para 2FA login
            // guarda session_token + flags. Para recovery codes
            // guarda el user_agent o ip del consumo.
            $table->json(column: 'context')->nullable();

            // TTL del challenge. Index para queries de limpieza
            // (challenges expirados > 24h).
            $table->timestamp(column: 'expires_at')->index();

            // Cuándo fue consumido (usado). null = todavía no usado.
            $table->timestamp(column: 'consumed_at')->nullable();

            // Contador de intentos fallidos. Para rate limiting:
            // después de N intentos fallidos el challenge se invalida
            // y el user debe generar uno nuevo.
            $table->unsignedInteger(column: 'attempts')->default(value: 0);

            $table->timestamps();

            // Índice compuesto para encontrar challenges activos
            // (no expirados, no consumidos) por user.
            $table->index(
                columns: ['user_id', 'consumed_at', 'expires_at'],
                name: 'otp_challenges_active_idx',
            );
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'otp_challenges');
    }
};
