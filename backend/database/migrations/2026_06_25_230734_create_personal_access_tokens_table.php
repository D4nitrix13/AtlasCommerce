<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de tokens de acceso personal utilizados
     * para autenticación de API por parte de Laravel Sanctum.
     */
    public function up(): void
    {
        Schema::create(table: 'personal_access_tokens', callback: function (Blueprint $table): void {
            $table->id();
            // Relación polimórfica: el token puede pertenecer a cualquier modelo (User, etc.)
            $table->morphs(name: 'tokenable');
            // Nombre descriptivo del token para identificación en el panel de administración
            $table->text(column: 'name');
            // Token hasheado de 64 caracteres, único para evitar duplicados
            $table->string(column: 'token', length: 64)->unique();
            // Capacidades/permisos del token en formato JSON serializado, nullable por defecto
            $table->text(column: 'abilities')->nullable();
            $table->timestamp(column: 'last_used_at')->nullable();
            // Fecha de expiración del token, indexada para limpieza periódica
            $table->timestamp(column: 'expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'personal_access_tokens');
    }
};
