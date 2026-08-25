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
     * Añade la columna `preferred_recovery_method` a la tabla `users`.
     * Determina qué método de recuperación de contraseña usa el user:
     *   - 'email' (default): código OTP de 6 dígitos vía email
     *   - '2fa': requiere TOTP code (el user tiene 2FA habilitado)
     *   - 'recovery_codes': requiere un recovery code alfanumérico
     *     (sin necesidad de email ni 2FA)
     *
     * El user puede cambiar este método en cualquier momento desde el
     * dashboard de seguridad. Cambiar el método NO invalida los
     * demás códigos activos (ej. si tenía 2FA puede desactivarla pero
     * conservar los recovery codes para re-generar después).
     */
    public function up(): void
    {
        Schema::table(table: 'users', callback: function (Blueprint $table): void {
            // Indexable para queries que filtren por método (ej. report
            // de cuántos users usan cada método para audit/migration).
            $table->string(column: 'preferred_recovery_method', length: 20)
                ->default(value: 'email')
                ->after(column: 'locked_until')
                ->index();
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table(table: 'users', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: 'preferred_recovery_method');
        });
    }
};
