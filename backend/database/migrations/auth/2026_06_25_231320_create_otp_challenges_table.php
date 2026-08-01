<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `otp_challenges` que gestiona los ciclos de vida de
     * códigos OTP: generación, intentos de verificación, expiración y uso.
     */
    public function up(): void
    {
        Schema::create(table: 'otp_challenges', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // UUID único para identificar cada desafío de forma segura en URLs y tokens
            $table->uuid(column: 'challenge_uuid')->unique();

            // Nullable porque algunos desafíos pueden ser anónimos (ej: reset de contraseña sin sesión)
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // Tipo de OTP: email_otp, totp, etc.
            $table->string(column: 'method', length: 30);
            // Propósito del OTP (login, reset_password, verify_email): index() para buscar desafíos activos por propósito
            $table->string(column: 'purpose', length: 60)->index();
            // Hash del código OTP, nunca se almacena el código en texto plano
            $table->string(column: 'code_hash');
            // Destino del OTP (email, teléfono): nullable para métodos que no requieren destino
            $table->string(column: 'destination')->nullable();

            // Contadores de intentos para prevenir fuerza bruta
            $table->unsignedSmallInteger('attempts')->default(value: 0);
            // Máximo de intentos permitidos antes de bloquear el desafío
            $table->unsignedSmallInteger('max_attempts')->default(value: 5);
            // Contador de reenvíos para rate limiting
            $table->unsignedSmallInteger('resend_count')->default(value: 0);

            // Timestamps de control: nullable para campos que aún no tienen valor
            // index() en expires_at para limpieza de desafíos expirados
            $table->timestamp(column: 'last_sent_at')->nullable();
            $table->timestamp(column: 'expires_at')->index();
            // index() en used_at para consultar desafíos completados
            $table->timestamp(column: 'used_at')->nullable()->index();
            // index() en locked_until para verificar bloqueos activos
            $table->timestamp(column: 'locked_until')->nullable()->index();

            // Metadatos de la petición para auditoría y detección de fraude
            $table->string(column: 'request_ip', length: 45)->nullable();
            // User agent completo del navegador/dispositivo que solicitó el OTP
            $table->text(column: 'user_agent')->nullable();

            $table->timestamps();

            // Índices compuestos optimizados para las consultas más frecuentes:
            // Buscar desafíos activos de un usuario por propósito antes de expirar
            $table->index(columns: ['user_id', 'purpose', 'expires_at'], name: 'otp_user_purpose_expires_idx');
            // Estadísticas de uso por método y propósito
            $table->index(columns: ['method', 'purpose'], name: 'otp_method_purpose_idx');
            // Verificar duplicados de destino por propósito (evitar múltiples OTPs al mismo email)
            $table->index(columns: ['destination', 'purpose'], name: 'otp_destination_purpose_idx');
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
