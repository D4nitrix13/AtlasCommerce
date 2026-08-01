<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de compras para gestionar las órdenes
     * de compra a proveedores del sistema.
     */
    public function up(): void
    {
        Schema::create(table: 'purchases', callback: function (Blueprint $table): void {
            $table->id();

            // Relación con proveedor: restrictOnDelete porque no se debe eliminar un proveedor que tenga compras registradas
            $table->foreignId(column: 'supplier_id')
                ->constrained(table: 'suppliers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Usuario que registró la compra, nullable si es una compra importada o automática
            // nullOnDelete: si se elimina el usuario, se conserva el registro de compra
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Número único de referencia de la compra para identificación interna
            $table->string(column: 'purchase_number', length: 80)->unique();
            // Montos financieros con 2 decimales para precisión monetaria
            $table->decimal(column: 'subtotal', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'tax', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'discount', total: 12, places: 2)->default(value: 0);
            $table->decimal(column: 'total', total: 12, places: 2)->default(value: 0);

            // Estado de la compra: 'pending', 'confirmed', 'received', 'cancelled', etc.
            $table->string(column: 'status', length: 50)->default(value: 'pending');
            // Fecha de la compra, se establece automáticamente al crear
            $table->timestamp(column: 'purchase_date')->useCurrent();
            // Observaciones o notas adicionales sobre la compra
            $table->text(column: 'notes')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de compras eliminadas
            $table->softDeletes();

            $table->index(columns: ['supplier_id'], name: 'purchases_supplier_id_idx');
            $table->index(columns: ['user_id'], name: 'purchases_user_id_idx');
            $table->index(columns: ['purchase_number'], name: 'purchases_purchase_number_idx');
            $table->index(columns: ['status'], name: 'purchases_status_idx');
            $table->index(columns: ['purchase_date'], name: 'purchases_purchase_date_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'purchases');
    }
};
