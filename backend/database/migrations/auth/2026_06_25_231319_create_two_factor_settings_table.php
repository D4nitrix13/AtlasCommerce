<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `two_factor_settings` que almacena la configuración
     * de autenticación de dos factores por usuario. Relación 1:1 con users.
     */
    public function up(): void
    {
        Schema::create(table: 'two_factor_settings', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // unique() porque es relación 1:1: cada usuario tiene una sola configuración de 2FA
            // Cascade en delete: si se elimina el usuario, se elimina su configuración 2FA
            $table->foreignId(column: 'user_id')
                ->unique()
                ->constrained(table: 'users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->boolean(column: 'enabled')->default(value: false);
            // enum limita a métodos soportados: email_otp, totp, passkey
            // nullable porque puede no tener método preferido hasta que configure 2FA
            $table->enum(column: 'preferred_method', allowed: ['email_otp', 'totp', 'passkey'])->nullable();
            // Si es true, el usuario debe completar 2FA para acceder a funcionalidades críticas
            $table->boolean(column: 'required')->default(value: false);
            // Timestamp de cuándo se habilitó 2FA por primera vez
            $table->timestamp(column: 'enabled_at')->nullable();

            $table->timestamps();

            // Índices para consultas que buscan usuarios con 2FA habilitado
            // y para filtrar por método preferido en configuración masiva
            $table->index(columns: ['enabled']);
            $table->index(columns: ['preferred_method'], name: 'two_factor_settings_preferred_method_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'two_factor_settings');
    }
};
