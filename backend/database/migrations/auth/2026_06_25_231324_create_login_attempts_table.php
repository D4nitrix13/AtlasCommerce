<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `login_attempts` que almacena un registro de cada
     * intento de login con metadatos completos para auditoría y seguridad.
     */
    public function up(): void
    {
        Schema::create(table: 'login_attempts', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Nullable porque el usuario puede no existir aún (intento con email no registrado)
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // index() en email para buscar intentos por email rápidamente
            $table->string(column: 'email')->index();
            // index() en successful para filtrar intentos exitosos/fallidos
            $table->boolean(column: 'successful')->default(value: false)->index();
            // Razón del fallo (credentials_invalid, account_locked, etc.)
            // index() para agrupar fallos por razón
            $table->string(column: 'failure_reason')->nullable()->index();
            // index() en ip para detectar intentos desde la misma IP
            $table->string(column: 'ip', length: 45)->nullable()->index();
            // User agent completo del navegador/dispositivo
            $table->text(column: 'user_agent')->nullable();
            // País y ciudad geolocalizados a partir de la IP
            $table->string(column: 'country', length: 100)->nullable();
            $table->string(column: 'city', length: 100)->nullable();
            // Marcado como sospechoso por reglas de seguridad
            // index() para filtrar intentos sospechosos
            $table->boolean(column: 'suspicious')->default(value: false)->index();
            // index() en attempted_at para consultas temporales y limpieza
            $table->timestamp(column: 'attempted_at')->index();

            $table->timestamps();

            // Índices compuestos optimizados para las consultas más frecuentes:
            // Historial de intentos por email ordenados cronológicamente
            $table->index(columns: ['email', 'attempted_at'], name: 'login_attempts_email_time_idx');
            // Intentos desde una IP específica en orden temporal
            $table->index(columns: ['ip', 'attempted_at'], name: 'login_attempts_ip_time_idx');
            // Conteo de intentos exitosos/fallidos por usuario
            $table->index(columns: ['user_id', 'successful'], name: 'login_attempts_user_success_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'login_attempts');
    }
};
