<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de transacciones de pago para registrar
     * las interacciones con proveedores de pago externos.
     */
    public function up(): void
    {
        Schema::create(table: 'payment_transactions', callback: function (Blueprint $table): void {
            $table->id();

            // Pago al que pertenece esta transacción: cascadeOnDelete porque la transacción no tiene sentido sin el pago
            $table->foreignId(column: 'payment_id')
                ->constrained(table: 'payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Nombre del proveedor de pago (ej: 'stripe', 'paypal', 'local_bank')
            $table->string(column: 'payment_provider', length: 80);
            // Código de transacción devuelto por el proveedor para referencia externa
            $table->string(column: 'transaction_code', length: 160)->nullable();
            // Respuesta completa del proveedor en JSON serializado para debugging y auditoría
            $table->longText(column: 'provider_response')->nullable();
            // Estado de la transacción: 'pending', 'success', 'failed', 'cancelled', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que se procesó la transacción con el proveedor
            $table->timestamp(column: 'transacted_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['payment_id'], name: 'payment_transactions_payment_id_idx');
            $table->index(columns: ['payment_provider'], name: 'payment_transactions_payment_provider_idx');
            $table->index(columns: ['transaction_code'], name: 'payment_transactions_transaction_code_idx');
            $table->index(columns: ['status'], name: 'payment_transactions_status_idx');
            $table->index(columns: ['transacted_at'], name: 'payment_transactions_transacted_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'payment_transactions');
    }
};
