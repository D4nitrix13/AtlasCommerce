<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'orders' que almacena las órdenes de compra del sistema de ventas.
     * Cada orden registra el cliente, dirección de envío, tienda y canal de venta asociados,
     * junto con los montos desglosados (subtotal, descuento, impuesto, total) y su estado actual.
     * Se incluyen campos de auditoría para rastrear el usuario que creó la orden y timestamps.
     */
    public function up(): void
    {
        Schema::create(table: 'orders', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // La dirección de envío es opcional ya que algunas órdenes pueden ser retiro en tienda
            $table->foreignId(column: 'shipping_address_id')
                ->nullable()
                ->constrained(table: 'customer_addresses')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // La tienda es opcional para órdenes creadas desde el panel de administración
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // El canal de venta permite identificar si la orden proviene de la web, POS, teléfono, etc.
            $table->foreignId(column: 'sales_channel_id')
                ->nullable()
                ->constrained(table: 'sales_channels')
                ->nullOnDelete();

            // Registro del usuario administrador que creó la orden (para órdenes manuales)
            $table->foreignId(column: 'user_creator_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            $table->string(column: 'order_number', length: 80)->unique();

            $table->decimal(column: 'subtotal', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'discount', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'tax', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'total', total: 12, places: 2)->default(value: 0);

            $table->string(column: 'status', length: 50)->default(value: 'pending');
            $table->timestamp(column: 'ordered_at')->useCurrent();

            $table->text(column: 'notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'orders_customer_id_idx');
            $table->index(columns: ['shipping_address_id'], name: 'orders_shipping_address_id_idx');
            $table->index(columns: ['store_id'], name: 'orders_store_id_idx');
            $table->index(columns: ['sales_channel_id'], name: 'orders_sales_channel_id_idx');
            $table->index(columns: ['user_creator_id'], name: 'orders_user_creator_id_idx');
            $table->index(columns: ['order_number'], name: 'orders_order_number_idx');
            $table->index(columns: ['status'], name: 'orders_status_idx');
            $table->index(columns: ['ordered_at'], name: 'orders_ordered_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'orders' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'orders');
    }
};
