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
     * Crea la tabla de cupones de descuento para gestionar
     * las promociones por código en el store.
     */
    public function up(): void
    {
        Schema::create(table: 'coupons', callback: function (Blueprint $table): void {
            $table->id();

            // Store al que pertenece el cupón, nullable para cupones globales del sistema
            // restrictOnDelete: no se debe eliminar un store que tenga cupones configurados
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Código único del cupón que los clientes ingresan al comprar
            $table->string(column: 'code', length: 80)->unique();
            // Tipo de descuento: 'percentage', 'fixed', 'free_shipping', etc.
            $table->string(column: 'discount_type', length: 50);
            // Valor del descuento (porcentaje o monto fijo según el tipo)
            $table->decimal(column: 'value', total: 12, places: 2);
            // Monto mínimo de compra requerido para aplicar el cupón, 0 sin mínimo
            $table->decimal(column: 'minimum_amount', total: 12, places: 2)->default(value: 0);
            // Fecha de inicio de vigencia, nullable para cupones sin fecha de inicio
            $table->date(column: 'starts_at')->nullable();
            // Fecha de fin de vigencia, nullable para cupones sin fecha de expiración
            $table->date(column: 'ends_at')->nullable();
            // Límite máximo de usos totales del cupón, nullable para uso ilimitado
            $table->unsignedInteger(column: 'usage_limit')->nullable();
            // Contador de usos actuales del cupón para controlar el límite
            $table->unsignedInteger(column: 'used_count')->default(value: 0);
            // Estado de activación: permite pausar cupones sin eliminarlos
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de cupones eliminados
            $table->softDeletes();

            $table->index(columns: ['store_id'], name: 'coupons_store_id_idx');
            $table->index(columns: ['code'], name: 'coupons_code_idx');
            $table->index(columns: ['discount_type'], name: 'coupons_discount_type_idx');
            $table->index(columns: ['starts_at'], name: 'coupons_starts_at_idx');
            $table->index(columns: ['ends_at'], name: 'coupons_ends_at_idx');
            $table->index(columns: ['is_active'], name: 'coupons_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'coupons');
    }
};
