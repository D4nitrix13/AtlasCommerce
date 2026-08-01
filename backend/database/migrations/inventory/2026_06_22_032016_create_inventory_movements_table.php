<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de movimientos de inventario para registrar
     * cada cambio de stock con trazabilidad completa.
     */
    public function up(): void
    {
        Schema::create(table: 'inventory_movements', callback: function (Blueprint $table): void {
            $table->id();

            // Cada movimiento pertenece a un registro de inventario específico
            $table->foreignId(column: 'inventory_id')
                ->constrained(table: 'inventories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Usuario que realizó el movimiento nullable: puede ser un movimiento automático del sistema
            // nullOnDelete: si se elimina el usuario, se conserva el registro del movimiento
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Tipo de movimiento: 'entry', 'exit', 'transfer', 'adjustment', etc.
            $table->string(column: 'movement_type', length: 50);
            // Cantidad movida (siempre positiva, el tipo determina si es entrada o salida)
            $table->unsignedInteger(column: 'quantity');

            // Stock antes del movimiento para auditoría y trazabilidad
            $table->unsignedInteger(column: 'previous_stock')->default(value: 0);
            // Stock después del movimiento para auditoría y trazabilidad
            $table->unsignedInteger(column: 'new_stock')->default(value: 0);

            // Razón o justificación del movimiento, nullable por si no se requiere explicación
            $table->text(column: 'reason')->nullable();
            // Fecha y hora del movimiento, se establece automáticamente al crear
            $table->timestamp(column: 'movement_date')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['inventory_id'], name: 'inventory_movements_inventory_id_idx');
            $table->index(columns: ['user_id'], name: 'inventory_movements_user_id_idx');
            $table->index(columns: ['movement_type'], name: 'inventory_movements_movement_type_idx');
            $table->index(columns: ['movement_date'], name: 'inventory_movements_movement_date_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'inventory_movements');
    }
};
