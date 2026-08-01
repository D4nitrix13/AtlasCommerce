<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla `recovery_codes`.
 *
 * Almacena hashes bcrypt de códigos de recuperación de uso único.
 * Los códigos en texto plano solo se muestran al usuario UNA SOLA VEZ
 * al momento de generarse.
 *
 * Formato del código: PREFIX-XXXX-XXXX (ej. APP-X7K2-M9P4).
 * El alfabeto excluye caracteres ambiguos (I, O, 0, 1).
 *
 * Índice [user_id, used]: optimiza la búsqueda de códigos no usados
 * durante la verificación.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->text('code_hash');

            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_codes');
    }
};
