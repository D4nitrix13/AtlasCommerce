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
     * Crea la tabla de notificaciones para gestionar los avisos
     * y mensajes enviados a usuarios del sistema.
     */
    public function up(): void
    {
        Schema::create(table: 'notifications', callback: function (Blueprint $table): void {
            $table->id();

            // Usuario destinatario de la notificación: cascadeOnDelete porque si se elimina el usuario sus notificaciones no tienen sentido
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Store asociado a la notificación, nullable para notificaciones generales del sistema
            // restrictOnDelete: no se debe eliminar un store que tenga notificaciones asociadas
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Título breve de la notificación para identificación rápida
            $table->string(column: 'title', length: 200);
            // Contenido detallado de la notificación
            $table->text(column: 'message');
            // Tipo de notificación: 'info', 'warning', 'error', 'success', etc.
            $table->string(column: 'type', length: 50)->default(value: 'info');
            // Estado de lectura: controla si el usuario ha visto la notificación
            $table->boolean(column: 'is_read')->default(value: false);
            // Fecha y hora en que el usuario leyó la notificación
            $table->timestamp(column: 'read_at')->nullable();

            $table->timestamps();

            $table->index(columns: ['user_id'], name: 'notifications_user_id_idx');
            $table->index(columns: ['store_id'], name: 'notifications_store_id_idx');
            $table->index(columns: ['type'], name: 'notifications_type_idx');
            $table->index(columns: ['is_read'], name: 'notifications_is_read_idx');
            $table->index(columns: ['read_at'], name: 'notifications_read_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'notifications');
    }
};
