<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea las tablas `users` y `sessions`.
     * - `users`: almacena los datos principales de los usuarios del sistema,
     *   incluyendo autenticación, perfil básico, y estado de cuenta.
     * - `sessions`: almacena las sesiones activas de los usuarios para
     *   gestión de sesiones concurrentes y control de acceso.
     */
    public function up(): void
    {
        Schema::create(table: 'users', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Relación con roles: nullable porque el usuario puede existir sin rol asignado
            $table->foreignId(column: 'role_id')
                ->nullable()
                ->constrained(table: 'roles')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'first_name', length: 80);
            $table->string(column: 'last_name', length: 80);
            $table->string(column: 'email', length: 150)->unique();
            // Timestamp de verificación de email: nullable hasta que el usuario verifique
            $table->timestamp(column: 'email_verified_at')->nullable();
            $table->string(column: 'password');
            // Teléfono opcional para usuarios que no proporcionan uno
            $table->string(column: 'phone', length: 30)->nullable();
            $table->boolean(column: 'is_active')->default(value: true);
            // Fecha de registro automática al crear la cuenta
            $table->timestamp(column: 'registered_at')->useCurrent();

            // Auditoría de seguridad: campos para rastrear actividad de login
            // index() para búsquedas frecuentes de usuarios con contraseñas recién cambiadas
            $table->timestamp(column: 'password_changed_at')->nullable()->index();
            // index() para ordenar y filtrar por último login
            $table->timestamp(column: 'last_login_at')->nullable()->index();
            // IP del último login, max 45 chars para IPv6
            $table->string(column: 'last_login_ip', length: 45)->nullable();
            $table->unsignedInteger(column: 'failed_login_attempts')->default(value: 0);
            // Bloqueo temporal tras intentos fallidos: index() para limpieza periódica
            $table->timestamp(column: 'locked_until')->nullable()->index();

            $table->rememberToken();
            $table->timestamps();
            // Soft deletes para mantener integridad referencial con tablas hijas
            $table->softDeletes();

            // Índices para joins y filtros frecuentes
            $table->index(columns: ['role_id'], name: 'users_role_id_idx');
            $table->index(columns: ['email'], name: 'users_email_idx');
            $table->index(columns: ['is_active'], name: 'users_is_active_idx');
        });

        Schema::create(table: 'sessions', callback: function (Blueprint $table): void {
            // ID de sesión como string (Laravel usa IDs de sesión aleatorios)
            $table->string(column: 'id')->primary();

            // Relación con usuarios: nullable para sesiones huérfanas durante limpieza
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->index()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // IP del cliente, max 45 para soportar IPv6
            $table->string(column: 'ip_address', length: 45)->nullable();
            // User agent completo del navegador/dispositivo
            $table->text(column: 'user_agent')->nullable();
            // Payload serializado de la sesión (datos del usuario, permisos, etc.)
            $table->longText(column: 'payload');
            // index() para limpieza de sesiones expiradas por actividad
            $table->integer(column: 'last_activity')->index();
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        // Primero sessions porque depende de users (foreign key)
        Schema::dropIfExists(table: 'sessions');
        Schema::dropIfExists(table: 'users');
    }
};
