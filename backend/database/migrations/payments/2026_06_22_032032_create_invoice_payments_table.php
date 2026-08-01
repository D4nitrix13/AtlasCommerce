<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de relación entre pagos y facturas para
     * registrar la aplicación de montos pagados a facturas específicas.
     */
    public function up(): void
    {
        Schema::create(table: 'invoice_payments', callback: function (Blueprint $table): void {
            $table->id();

            // Factura a la que se aplica el pago: cascadeOnDelete porque si se elimina la factura la relación no tiene sentido
            $table->foreignId(column: 'invoice_id')
                ->constrained(table: 'invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Pago aplicado a la factura: cascadeOnDelete porque si se elimina el pago la relación no tiene sentido
            $table->foreignId(column: 'payment_id')
                ->constrained(table: 'payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Monto del pago aplicado a esta factura específica (permite pagos parciales)
            $table->decimal(column: 'applied_amount', total: 12, places: 2);
            // Fecha y hora en que se aplicó el pago a la factura
            $table->timestamp(column: 'applied_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['invoice_id'], name: 'invoice_payments_invoice_id_idx');
            $table->index(columns: ['payment_id'], name: 'invoice_payments_payment_id_idx');
            $table->index(columns: ['applied_at'], name: 'invoice_payments_applied_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'invoice_payments');
    }
};
