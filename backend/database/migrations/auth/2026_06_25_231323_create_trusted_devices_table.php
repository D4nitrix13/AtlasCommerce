<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `trusted_devices` que almacena dispositivos en los que
     * el usuario eligió confiar, con su huella digital y metadatos de uso.
     */
    public function up(): void
    {
        Schema::create(table: 'trusted_devices', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cascade on delete: eliminar usuario elimina sus dispositivos de confianza
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // Hash de la huella digital del dispositivo (no el valor crudo por seguridad)
            $table->string(column: 'fingerprint_hash');
            // Nombre descriptivo del dispositivo para que el usuario lo identifique
            $table->string(column: 'device_name')->nullable();
            // IP del último uso, max 45 para IPv6
            $table->string(column: 'last_ip', length: 45)->nullable();
            // User agent del último uso para auditoría
            $table->text(column: 'last_user_agent')->nullable();
            // Fecha en que el usuario marcó el dispositivo como de confianza
            $table->timestamp(column: 'trusted_at')->nullable();
            // index() en last_used_at para ordenar dispositivos por uso reciente
            $table->timestamp(column: 'last_used_at')->nullable()->index();
            // index() en expires_at para limpiar dispositivos expirados
            $table->timestamp(column: 'expires_at')->nullable()->index();
            // index() en revoked_at para filtrar dispositivos revocados
            $table->timestamp(column: 'revoked_at')->nullable()->index();

            $table->timestamps();

            // unique() compuesto para evitar duplicados de la misma huella por usuario
            $table->unique(columns: ['user_id', 'fingerprint_hash'], name: 'trusted_user_fingerprint_unique');
            // Índices compuestos para consultas frecuentes:
            // Buscar dispositivos que expiraron para un usuario
            $table->index(columns: ['user_id', 'expires_at'], name: 'trusted_user_expires_idx');
            // Buscar dispositivos revocados de un usuario
            $table->index(columns: ['user_id', 'revoked_at'], name: 'trusted_user_revoked_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'trusted_devices');
    }
};
