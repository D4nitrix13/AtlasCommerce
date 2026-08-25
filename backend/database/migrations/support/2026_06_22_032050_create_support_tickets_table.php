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
     * Crea la tabla de tickets de soporte para gestionar
     * las solicitudes de ayuda de los clientes del store.
     */
    public function up(): void
    {
        Schema::create(table: 'support_tickets', callback: function (Blueprint $table): void {
            $table->id();

            // Cliente que creó el ticket: cascadeOnDelete porque si se elimina el cliente sus tickets no tienen sentido en el sistema activo
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Store asociado al ticket, nullable para tickets de soporte general del sistema
            // restrictOnDelete: no se debe eliminar un store que tenga tickets de soporte activos
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Asunto del ticket para identificación rápida del problema
            $table->string(column: 'subject', length: 200);
            // Descripción detallada del problema o solicitud
            $table->text(column: 'description');
            // Estado del ticket: 'open', 'in_progress', 'resolved', 'closed', etc.
            $table->string(column: 'status', length: 50)->default(value: 'open');
            // Prioridad del ticket: 'low', 'normal', 'high', 'urgent'
            $table->string(column: 'priority', length: 50)->default(value: 'normal');

            $table->timestamps();
            // Soft deletes para conservar historial de tickets eliminados
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'support_tickets_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'support_tickets_store_id_idx');
            $table->index(columns: ['status'], name: 'support_tickets_status_idx');
            $table->index(columns: ['priority'], name: 'support_tickets_priority_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'support_tickets');
    }
};
