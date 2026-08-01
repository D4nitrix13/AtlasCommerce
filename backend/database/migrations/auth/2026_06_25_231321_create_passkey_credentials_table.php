<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `passkey_credentials` que almacena las credenciales
     * FIDO2/WebAuthn de los usuarios para autenticación sin contraseña.
     */
    public function up(): void
    {
        Schema::create(table: 'passkey_credentials', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cascade on delete: si se elimina el usuario, se eliminan sus passkeys
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // ID de credencial WebAuthn, longitud grande para soportar cualquier implementación
            // unique() porque cada credencial es única a nivel mundial
            $table->string(column: 'credential_id', length: 1024)->unique();
            // Clave pública PEM del dispositivo
            $table->text(column: 'public_key');
            // Contador de autenticación para detectar clonación de credenciales
            $table->unsignedBigInteger(column: 'sign_count')->default(value: 0);
            // Transportes soportados (usb, nfc, ble, internal): jsonb para consultas eficientes
            $table->jsonb(column: 'transports')->nullable();
            // AAGUID identifica el fabricante/tipo de autenticador
            $table->string(column: 'aaguid', length: 100)->nullable();
            // Nombre descriptivo del dispositivo para que el usuario lo identifique
            $table->string(column: 'device_name', length: 100)->nullable();

            // Indica si la passkey puede ser respaldada (sync entre dispositivos)
            $table->boolean(column: 'backup_eligible')->default(value: false);
            // Estado actual del respaldo (true si la passkey está sincronizada)
            $table->boolean(column: 'backup_state')->default(value: false);

            // Timestamp del último uso para auditoría
            $table->timestamp(column: 'last_used_at')->nullable();
            $table->boolean(column: 'active')->default(value: true);
            // Timestamp de revocación: index() para filtrar passkeys revocadas
            $table->timestamp(column: 'revoked_at')->nullable()->index();

            $table->timestamps();

            // Índices compuestos para consultas frecuentes:
            // Buscar passkeys activas de un usuario
            $table->index(columns: ['user_id', 'active'], name: 'passkeys_user_active_idx');
            // Buscar passkeys revocadas de un usuario para limpieza
            $table->index(columns: ['user_id', 'revoked_at'], name: 'passkeys_user_revoked_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'passkey_credentials');
    }
};
