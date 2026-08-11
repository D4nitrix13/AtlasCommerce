<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `two_factor_settings` que guarda la configuración
     * TOTP por usuario. Una fila por usuario que haya activado 2FA.
     *
     * - `secret`: secret TOTP encriptado con Laravel Crypt (nunca se
     *   expone al frontend — el QR se genera server-side, solo se
     *   muestra en el setup).
     * - `enabled_at`: null = 2FA deshabilitado. Timestamp no-null =
     *   2FA habilitado desde esa fecha.
     * - `last_used_at`: rate limiting y audit (rechaza TOTP codes
     *   que se usaron hace menos de N segundos para evitar replays).
     * - `recovery_codes_generated_at`: cuándo se generaron los
     *   recovery codes actuales. null = no hay codes generados.
     */
    public function up(): void
    {
        Schema::create(table: 'two_factor_settings', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // FK one-to-one con users. unique() para que cada user tenga
            // a lo sumo una config 2FA.
            $table->foreignId(column: 'user_id')
                ->unique()
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // TOTP secret encryptado con Laravel Crypt facade.
            // 255 chars cubre el base32 de 32 bytes (52 chars) más
            // headers de Laravel Crypt (iv + hmac + base64 encoding).
            $table->string(column: 'secret', length: 255);

            // null = 2FA deshabilitado. Index para queries que filtran
            // por usuarios con 2FA activo.
            $table->timestamp(column: 'enabled_at')->nullable()->index();

            // null hasta que el user use 2FA por primera vez. Para
            // rate limiting y audit.
            $table->timestamp(column: 'last_used_at')->nullable();

            // null = no se generaron recovery codes. Index para
            // encontrar codes de un user para regenerate.
            $table->timestamp(column: 'recovery_codes_generated_at')->nullable();

            $table->timestamps();
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
