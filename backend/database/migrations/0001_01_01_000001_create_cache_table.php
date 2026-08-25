<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea las tablas de caché y bloqueos de caché utilizadas
     * por el sistema de caché de Laravel.
     */
    public function up(): void
    {
        Schema::create(table: 'cache', callback: function (Blueprint $table): void {
            $table->string(column: 'key')->primary();
            $table->mediumText(column: 'value');
            // Índice en expiration para búsquedas por tiempo de expiración
            $table->bigInteger(column: 'expiration')->index();
        });

        Schema::create(table: 'cache_locks', callback: function (Blueprint $table): void {
            $table->string(column: 'key')->primary();
            // Owner identifica qué proceso instanció el bloqueo
            $table->string(column: 'owner');
            $table->bigInteger(column: 'expiration')->index();
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'cache_locks');
        Schema::dropIfExists(table: 'cache');
    }
};
