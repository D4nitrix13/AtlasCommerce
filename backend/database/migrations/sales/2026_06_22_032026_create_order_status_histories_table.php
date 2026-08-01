<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'order_status_histories' que registra el historial de cambios de estado
     * de cada orden. Permite rastrear quién realizó el cambio, el estado anterior, el nuevo
     * estado, una observación opcional y la fecha/hora del cambio.
     */
    public function up(): void
    {
        Schema::create(table: 'order_status_histories', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'order_id')
                ->constrained(table: 'orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // El usuario es opcional cuando el cambio lo realiza el sistema automáticamente
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // El estado anterior es nulo cuando se crea la primera entrada del historial
            $table->string(column: 'previous_status', length: 50)->nullable();
            $table->string(column: 'new_status', length: 50);
            $table->text(column: 'observation')->nullable();
            $table->timestamp(column: 'changed_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['order_id'], name: 'order_status_histories_order_id_idx');
            $table->index(columns: ['user_id'], name: 'order_status_histories_user_id_idx');
            $table->index(columns: ['previous_status'], name: 'order_status_histories_previous_status_idx');
            $table->index(columns: ['new_status'], name: 'order_status_histories_new_status_idx');
            $table->index(columns: ['changed_at'], name: 'order_status_histories_changed_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'order_status_histories' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'order_status_histories');
    }
};
