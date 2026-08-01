<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla `personal_access_tokens` (Laravel Sanctum).
 *
 * Tabla de infraestructura técnica que almacena los tokens de API
 * utilizados por el middleware `auth:sanctum`. No es una entidad
 * del dominio del store, por eso se mantiene en la raíz de
 * `database/migrations/` en lugar de una subcarpeta de módulo.
 *
 * Todas las rutas 2FA están protegidas por este middleware y
 * requieren un token válido en el header `Authorization: Bearer {token}`.
 *
 * @see https://docs.laravel.com/sanctum
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
