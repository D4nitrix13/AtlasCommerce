<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla `two_factor_settings`.
 *
 * Almacena la configuración global de 2FA por usuario (relación 1:1 con users).
 * Controla si 2FA está habilitado, el método preferido y si es obligatorio.
 *
 * Esta tabla se crea/actualiza cuando el usuario activa o desactiva 2FA.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('two_factor_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);
            $table->enum('preferred_method', ['email_otp', 'totp', 'passkey'])->nullable();
            $table->boolean('required')->default(false);
            $table->timestamp('enabled_at')->nullable();

            $table->timestamps();

            $table->index('enabled');
            $table->index('preferred_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_settings');
    }
};
