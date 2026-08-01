<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de auditorías para registrar todas las
     * acciones importantes realizadas en el sistema.
     */
    public function up(): void
    {
        Schema::create(table: 'audits', callback: function (Blueprint $table): void {
            $table->id();

            // Usuario que realizó la acción, nullable para acciones del sistema o automatizadas
            // nullOnDelete: si se elimina el usuario, se conserva el registro de auditoría como historial
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Store donde se realizó la acción, nullable para auditorías globales del sistema
            // restrictOnDelete: no se debe eliminar un store que tenga auditorías registradas
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Tipo de acción realizada (ej: 'created', 'updated', 'deleted', 'login', etc.)
            $table->string(column: 'action', length: 80);
            // Nombre de la tabla afectada por la acción
            $table->string(column: 'table_name', length: 100);
            // ID del registro afectado, nullable para acciones que no afectan registros específicos
            $table->string(column: 'record_id', length: 50)->nullable();
            // Descripción legible de la acción realizada
            $table->text(column: 'description')->nullable();
            // Valores anteriores del registro en formato JSON serializado para comparación
            $table->json(column: 'old_values')->nullable();
            // Valores nuevos del registro en formato JSON serializado para comparación
            $table->json(column: 'new_values')->nullable();
            // Dirección IP del cliente que realizó la acción, nullable para acciones del sistema
            $table->string(column: 'ip_address', length: 45)->nullable();
            // User-Agent del navegador del cliente, nullable para acciones del sistema
            $table->text(column: 'user_agent')->nullable();
            // Fecha y hora en que se registró la auditoría
            $table->timestamp(column: 'registered_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['user_id'], name: 'audits_user_id_idx');
            $table->index(columns: ['store_id'], name: 'audits_store_id_idx');
            $table->index(columns: ['action'], name: 'audits_action_idx');
            $table->index(columns: ['table_name'], name: 'audits_table_name_idx');
            $table->index(columns: ['record_id'], name: 'audits_record_id_idx');
            $table->index(columns: ['registered_at'], name: 'audits_registered_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'audits');
    }
};
