<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de envíos para gestionar las entregas
     * de pedidos realizados en el store.
     */
    public function up(): void
    {
        Schema::create(table: 'shipments', callback: function (Blueprint $table): void {
            $table->id();

            // Pedido asociado al envío: cascadeOnDelete porque si se elimina el pedido el envío no tiene sentido
            $table->foreignId(column: 'order_id')
                ->constrained(table: 'orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Empresa de envío nullable: permite registrar envíos sin empresa asignada temporalmente
            // nullOnDelete: si se elimina la empresa, el envío se mantiene pero sin empresa asociada
            $table->foreignId(column: 'shipping_company_id')
                ->nullable()
                ->constrained(table: 'shipping_companies')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Zona de envío nullable: permite registrar envíos sin zona asignada temporalmente
            // nullOnDelete: si se elimina la zona, el envío se mantiene pero sin zona asociada
            $table->foreignId(column: 'shipping_zone_id')
                ->nullable()
                ->constrained(table: 'shipping_zones')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Código de rastreo único devuelto por la empresa de envío, nullable hasta que se genere
            $table->string(column: 'tracking_code', length: 160)->nullable()->unique();
            // Costo total del envío cobrado al cliente
            $table->decimal(column: 'shipping_cost', total: 12, places: 2)->default(value: 0);
            // Estado del envío: 'pending', 'shipped', 'in_transit', 'delivered', 'returned', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha y hora en que el paquete fue despachado
            $table->timestamp(column: 'shipped_at')->nullable();
            // Fecha y hora en que el paquete fue entregado al destinatario
            $table->timestamp(column: 'delivered_at')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de envíos eliminados
            $table->softDeletes();

            $table->index(columns: ['order_id'], name: 'shipments_order_id_idx');
            $table->index(columns: ['shipping_company_id'], name: 'shipments_shipping_company_id_idx');
            $table->index(columns: ['shipping_zone_id'], name: 'shipments_shipping_zone_id_idx');
            $table->index(columns: ['tracking_code'], name: 'shipments_tracking_code_idx');
            $table->index(columns: ['status'], name: 'shipments_status_idx');
            $table->index(columns: ['shipped_at'], name: 'shipments_shipped_at_idx');
            $table->index(columns: ['delivered_at'], name: 'shipments_delivered_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'shipments');
    }
};
