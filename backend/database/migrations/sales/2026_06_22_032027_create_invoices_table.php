<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'invoices' que almacena las facturas generadas a partir de órdenes.
     * Cada factura registra la orden asociada (opcional), cliente, tienda, canal de venta
     * y el usuario emisor. Incluye montos desglosados (subtotal, descuento, impuesto, total),
     * saldos de pago (pagado, pendiente), estados de pago e factura, y fechas de emisión y vencimiento.
     * Se permite soft deletes para mantener historial de facturas anuladas.
     */
    public function up(): void
    {
        Schema::create(table: 'invoices', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // La orden es opcional para facturas generadas directamente sin orden previa
            $table->foreignId(column: 'order_id')
                ->nullable()
                ->constrained(table: 'orders')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // La tienda es opcional para facturas emitidas desde el panel administrativo
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            $table->foreignId(column: 'sales_channel_id')
                ->nullable()
                ->constrained(table: 'sales_channels')
                ->nullOnDelete();

            // Registro del usuario administrador que emitió la factura
            $table->foreignId(column: 'user_issuer_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            $table->string(column: 'invoice_number', length: 80)->unique();

            $table->decimal(column: 'subtotal', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'discount', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'tax', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'total', total: 12, places: 2)->default(value: 0);

            $table->decimal(column: 'paid_amount', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'pending_balance', total: 12, places: 2)->default(value: 0);

            $table->string(column: 'payment_status', length: 50)->default(value: 'pending');
            $table->string(column: 'invoice_status', length: 50)->default(value: 'issued');

            $table->timestamp(column: 'issued_at')->useCurrent();
            $table->timestamp(column: 'due_at')->nullable();

            $table->text(column: 'notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['order_id'], name: 'invoices_order_id_idx');
            $table->index(columns: ['customer_id'], name: 'invoices_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'invoices_store_id_idx');
            $table->index(columns: ['sales_channel_id'], name: 'invoices_sales_channel_id_idx');
            $table->index(columns: ['user_issuer_id'], name: 'invoices_user_issuer_id_idx');
            $table->index(columns: ['invoice_number'], name: 'invoices_invoice_number_idx');
            $table->index(columns: ['payment_status'], name: 'invoices_payment_status_idx');
            $table->index(columns: ['invoice_status'], name: 'invoices_invoice_status_idx');
            $table->index(columns: ['issued_at'], name: 'invoices_issued_at_idx');
            $table->index(columns: ['due_at'], name: 'invoices_due_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'invoices' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'invoices');
    }
};
