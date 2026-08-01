<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de reembolsos para gestionar la devolución
     * de montos a clientes por devoluciones aprobadas.
     */
    public function up(): void
    {
        Schema::create(table: 'refunds', callback: function (Blueprint $table): void {
            $table->id();

            // Solicitud de devolución asociada al reembolso: cascadeOnDelete porque si se elimina la solicitud el reembolso no tiene sentido
            $table->foreignId(column: 'return_request_id')
                ->constrained(table: 'return_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Método de pago para el reembolso, nullable temporalmente hasta asignar método
            // nullOnDelete: si se elimina el método de pago, el reembolso se mantiene pero sin método asociado
            $table->foreignId(column: 'payment_method_id')
                ->nullable()
                ->constrained(table: 'payment_methods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Monto total a reembolsar con precisión monetaria
            $table->decimal(column: 'amount', total: 12, places: 2);
            // Estado del reembolso: 'pending', 'processing', 'completed', 'failed', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que se ejecutó efectivamente el reembolso
            $table->timestamp(column: 'refunded_at')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de reembolsos eliminados
            $table->softDeletes();

            $table->index(columns: ['return_request_id'], name: 'refunds_return_request_id_idx');
            $table->index(columns: ['payment_method_id'], name: 'refunds_payment_method_id_idx');
            $table->index(columns: ['status'], name: 'refunds_status_idx');
            $table->index(columns: ['refunded_at'], name: 'refunds_refunded_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'refunds');
    }
};
