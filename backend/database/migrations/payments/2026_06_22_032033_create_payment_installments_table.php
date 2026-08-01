<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de cuotas de pago para gestionar pagos
     * a plazos de facturas del sistema.
     */
    public function up(): void
    {
        Schema::create(table: 'payment_installments', callback: function (Blueprint $table): void {
            $table->id();

            // Factura asociada a las cuotas: cascadeOnDelete porque si se elimina la factura las cuotas no tienen sentido
            $table->foreignId(column: 'invoice_id')
                ->constrained(table: 'invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Número secuencial de la cuota (1, 2, 3, etc.) para ordenamiento
            $table->unsignedInteger('installment_number');
            // Monto programado para esta cuota
            $table->decimal(column: 'scheduled_amount', total: 12, places: 2);
            // Monto efectivamente pagado de esta cuota, puede ser parcial
            $table->decimal(column: 'paid_amount', total: 12, places: 2)->default(value: 0);
            // Fecha de vencimiento de la cuota para control de mora
            $table->date(column: 'due_date');
            // Estado de la cuota: 'pending', 'paid', 'overdue', 'cancelled', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que se completó el pago de la cuota
            $table->timestamp(column: 'paid_at')->nullable();

            $table->timestamps();

            $table->index(columns: ['invoice_id'], name: 'payment_installments_invoice_id_idx');
            $table->index(columns: ['installment_number'], name: 'payment_installments_installment_number_idx');
            $table->index(columns: ['due_date'], name: 'payment_installments_due_date_idx');
            $table->index(columns: ['status'], name: 'payment_installments_status_idx');
            $table->index(columns: ['paid_at'], name: 'payment_installments_paid_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'payment_installments');
    }
};
