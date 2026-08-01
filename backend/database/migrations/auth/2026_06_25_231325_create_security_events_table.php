<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `security_events` que almacena un registro inmutable
     * de todos los eventos de seguridad del sistema con metadatos completos.
     */
    public function up(): void
    {
        Schema::create(table: 'security_events', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Nullable porque algunos eventos pueden ocurrir sin usuario asociado
            // (ej: intento de acceso con token inválido)
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // index() en email para buscar eventos por email
            $table->string(column: 'email')->nullable()->index();
            // Tipo de evento (password_changed, 2fa_enabled, login_failed, etc.)
            // index() para filtrar por tipo de evento
            $table->string(column: 'event_type', length: 80)->index();
            // Nivel de severidad (info, warning, critical): index() para filtrar por severidad
            $table->string(column: 'severity', length: 30)->default(value: 'info')->index();
            // index() en ip para detectar patrones por dirección IP
            $table->string(column: 'ip', length: 45)->nullable()->index();
            // User agent del navegador/dispositivo que originó el evento
            $table->text(column: 'user_agent')->nullable();
            // JSON con datos adicionales del evento (detalles específicos de cada tipo)
            // json() para almacenar metadata estructurada variable
            $table->json(column: 'metadata')->nullable();
            // index() en event_at para consultas temporales y ordenamiento
            $table->timestamp(column: 'event_at')->index();

            $table->timestamps();

            // Índices compuestos optimizados para las consultas más frecuentes:
            // Historial de eventos de seguridad por usuario
            $table->index(columns: ['user_id', 'event_type'], name: 'security_user_event_idx');
            // Eventos por tipo en orden cronológico (para dashboards y reportes)
            $table->index(columns: ['event_type', 'event_at'], name: 'security_event_time_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'security_events');
    }
};
