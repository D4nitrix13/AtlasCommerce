<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `two_factor_methods` que almacena cada método 2FA
     * registrado por los usuarios, con su estado de verificación y activación.
     */
    public function up(): void
    {
        Schema::create(table: 'two_factor_methods', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cascade en ambas direcciones: eliminar usuario elimina sus métodos 2FA
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // enum limita a los métodos 2FA soportados por el sistema
            $table->enum(column: 'method', allowed: ['email_otp', 'totp', 'passkey']);
            // Nombre visible para que el usuario identifique el método (ej: "Mi email personal")
            $table->string(column: 'display_name', length: 100)->nullable();
            // Destino del método (email, dirección de authenticator): nullable para passkeys
            $table->string(column: 'destination')->nullable();
            // Secreto cifrado para TOTP: nullable para métodos que no requieren secreto
            $table->text(column: 'encrypted_secret')->nullable();

            $table->boolean(column: 'active')->default(value: true);
            // Verificado indica que el usuario completó el proceso de setup
            $table->boolean(column: 'verified')->default(value: false);
            // Timestamp de cuándo se verificó el método por primera vez
            $table->timestamp(column: 'verified_at')->nullable();

            $table->timestamps();

            // Índices compuestos para consultas frecuentes:
            // Buscar métodos por tipo para un usuario específico
            $table->index(columns: ['user_id', 'method'], name: 'two_factor_methods_user_id_method_idx');
            // Filtrar solo métodos activos de un usuario
            $table->index(columns: ['user_id', 'active'], name: 'two_factor_methods_user_id_active_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'two_factor_methods');
    }
};
