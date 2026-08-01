<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de pagos para registrar las transacciones
     * de pago realizadas por los clientes del store.
     */
    public function up(): void
    {
        Schema::create(table: 'payments', callback: function (Blueprint $table): void {
            $table->id();

            // Cliente que realiza el pago: cascadeOnUpdate para reflejar cambios en datos del cliente
            // restrictOnDelete: no se debe eliminar un cliente que tenga pagos registrados
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Método de pago utilizado para esta transacción
            $table->foreignId(column: 'payment_method_id')
                ->constrained(table: 'payment_methods')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Store asociado al pago, nullable para pagos que no pertenecen a un store específico
            // restrictOnDelete: no se debe eliminar un store que tenga pagos registrados
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Monto total del pago con precisión monetaria de 2 decimales
            $table->decimal(column: 'amount', total: 12, places: 2);
            // Referencia externa del pago (número de transferencia, código de transacción, etc.)
            $table->string(column: 'reference', length: 160)->nullable();
            // Estado del pago: 'pending', 'completed', 'failed', 'refunded', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que se confirmó el pago, nullable hasta que se verifique
            $table->timestamp(column: 'paid_at')->nullable();
            // Notas o observaciones adicionales sobre el pago
            $table->text(column: 'notes')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de pagos eliminados
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'payments_customer_id_idx');
            $table->index(columns: ['payment_method_id'], name: 'payments_payment_method_id_idx');
            $table->index(columns: ['store_id'], name: 'payments_store_id_idx');
            $table->index(columns: ['reference'], name: 'payments_reference_idx');
            $table->index(columns: ['status'], name: 'payments_status_idx');
            $table->index(columns: ['paid_at'], name: 'payments_paid_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'payments');
    }
};
