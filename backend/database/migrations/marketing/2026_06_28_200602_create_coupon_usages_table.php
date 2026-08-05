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
     * Crea la tabla de usos de cupones para registrar cada vez
     * que un cliente aplica un cupón de descuento en una compra.
     */
    public function up(): void
    {
        Schema::create(table: 'coupon_usages', callback: function (Blueprint $table): void {
            $table->id();

            // Cupón utilizado: cascadeOnDelete porque si se elimina el cupón su historial de uso no tiene sentido
            $table->foreignId(column: 'coupon_id')
                ->constrained(table: 'coupons')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Cliente que utilizó el cupón: cascadeOnDelete porque el historial de uso se mantiene en esta tabla
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Pedido en el que se aplicó el cupón, nullable para usos de prueba ocupones sin pedido asociado
            // nullOnDelete: si se elimina el pedido, se conserva el registro de uso del cupón
            $table->foreignId(column: 'order_id')
                ->nullable()
                ->constrained(table: 'orders')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Fecha y hora en que se aplicó el cupón, se establece automáticamente
            $table->timestamp(column: 'used_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['coupon_id'], name: 'coupon_usages_coupon_id_idx');
            $table->index(columns: ['customer_id'], name: 'coupon_usages_customer_id_idx');
            $table->index(columns: ['order_id'], name: 'coupon_usages_order_id_idx');
            $table->index(columns: ['used_at'], name: 'coupon_usages_used_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'coupon_usages');
    }
};
