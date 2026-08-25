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
     * Crea la tabla 'order_custom_designs' que gestiona los diseños personalizados
     * asociados a los ítems de una orden. Almacena snapshots del diseño en JSON para
     * preservar el estado en el momento de la orden, archivos de producción, y un
     * flujo completo de revisión (aprobación/rechazo) y producción con timestamps.
     * Se usa unique en order_item_id para garantizar un solo diseño por ítem de orden.
     */
    public function up(): void
    {
        Schema::create(table: 'order_custom_designs', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'order_id')
                ->constrained(table: 'orders')
                ->cascadeOnDelete();

            $table->foreignId(column: 'order_item_id')
                ->constrained(table: 'order_items')
                ->cascadeOnDelete();

            // El diseño personalizado de catálogo es opcional; el snapshot preserva el diseño real
            $table->foreignId(column: 'custom_design_id')
                ->nullable()
                ->constrained(table: 'custom_designs')
                ->nullOnDelete();

            // Snapshot del diseño en JSON para preservar el estado exacto al momento de la orden
            $table->json(column: 'design_snapshot');
            // URL del archivo de producción generado a partir del diseño
            $table->string(column: 'production_file_url')->nullable();
            // Estado del proceso de producción: pending, in_production, completed, etc.
            $table->string(column: 'production_status', length: 40)->default(value: 'pending')->index();
            $table->text(column: 'production_notes')->nullable();

            // Flujo de revisión: el usuario que revisó y cuándo
            $table->foreignId(column: 'reviewed_by')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // Timestamps del flujo de revisión y producción para auditoría
            $table->timestamp(column: 'reviewed_at')->nullable()->index();
            $table->timestamp(column: 'approved_at')->nullable()->index();
            $table->timestamp(column: 'rejected_at')->nullable()->index();
            $table->text(column: 'rejection_reason')->nullable();

            // Timestamps del proceso de producción para seguimiento
            $table->timestamp(column: 'production_started_at')->nullable()->index();
            $table->timestamp(column: 'production_completed_at')->nullable()->index();

            $table->timestamps();

            // Un solo diseño personalizado por ítem de orden
            $table->unique(columns: 'order_item_id', name: 'order_custom_design_order_item_unique');
            // Índice compuesto para buscar diseños de una orden por estado de producción
            $table->index(columns: ['order_id', 'production_status'], name: 'order_custom_design_order_status_idx');
            // Índice compuesto para filtrar por estado y fecha de aprobación
            $table->index(columns: ['production_status', 'approved_at'], name: 'order_custom_designs_status_approved_idx');
            // Índice compuesto para filtrar revisiones por revisor y fecha
            $table->index(columns: ['reviewed_by', 'reviewed_at'], name: 'order_custom_designs_reviewer_time_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'order_custom_designs' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'order_custom_designs');
    }
};
