<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de solicitudes de devolución para gestionar
     * los procesos de devolución de productos del store.
     */
    public function up(): void
    {
        Schema::create(table: 'return_requests', callback: function (Blueprint $table): void {
            $table->id();

            // Pedido del que se solicita la devolución: restrictOnDelete porque no se debe eliminar un pedido con devoluciones activas
            $table->foreignId(column: 'order_id')
                ->constrained(table: 'orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Cliente que solicita la devolución: restrictOnDelete porque no se debe eliminar un cliente con historial de devoluciones
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Motivo detallado de la devolución (obligatorio para auditoría)
            $table->text(column: 'reason');
            // Estado de la solicitud: 'pending', 'approved', 'rejected', 'completed', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que se creó la solicitud
            $table->timestamp(column: 'requested_at')->useCurrent();
            // Fecha y hora en que se resolvió la solicitud (aprobada o rechazada)
            $table->timestamp(column: 'resolved_at')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de solicitudes de devolución eliminadas
            $table->softDeletes();

            $table->index(columns: ['order_id'], name: 'return_requests_order_id_idx');
            $table->index(columns: ['customer_id'], name: 'return_requests_customer_id_idx');
            $table->index(columns: ['status'], name: 'return_requests_status_idx');
            $table->index(columns: ['requested_at'], name: 'return_requests_requested_at_idx');
            $table->index(columns: ['resolved_at'], name: 'return_requests_resolved_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'return_requests');
    }
};
