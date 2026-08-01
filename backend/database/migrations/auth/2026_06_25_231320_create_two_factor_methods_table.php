<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla `two_factor_methods`.
 *
 * Almacena los métodos 2FA individuales registrados por cada usuario (relación N:1).
 * Un usuario puede tener múltiples métodos (email_otp, totp, passkey), cada uno
 * con su propio estado de verificación y activación.
 *
 * Índices compuestos optimizados para las consultas más frecuentes:
 * - [user_id, method]: buscar métodos por tipo para un usuario.
 * - [user_id, active]: filtrar solo métodos activos de un usuario.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('two_factor_methods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('method', ['email_otp', 'totp', 'passkey']);
            $table->string('display_name', 100)->nullable();
            $table->string('destination')->nullable();
            $table->text('encrypted_secret')->nullable();

            $table->boolean('active')->default(true);
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'method']);
            $table->index(['user_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_methods');
    }
};
