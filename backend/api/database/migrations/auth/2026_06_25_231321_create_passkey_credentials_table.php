<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla `passkey_credentials`.
 *
 * Almacena credenciales WebAuthn para autenticación con passkeys.
 * Preparado para la fase futura de integración con `laravel/passkeys`.
 *
 * Cada credencial contiene:
 * - `credential_id`: identificador único (hasta 1024 bytes para compatibilidad).
 * - `public_key`: clave pública del authenticator.
 * - `sign_count`: contador de firmas para detectar clonación.
 * - `transports`: transportes soportados (usb, nfc, ble, internal).
 * - `aaguid`: identificador del manufacturer del authenticator.
 *
 * Índice [user_id, active]: optimiza la búsqueda de credenciales activas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('passkey_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('credential_id', 1024)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->jsonb('transports')->nullable();
            $table->string('aaguid', 100)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkey_credentials');
    }
};
