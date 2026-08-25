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
     * Crea la tabla de promociones para gestionar ofertas
     * y descuentos automáticos del store.
     */
    public function up(): void
    {
        Schema::create(table: 'promotions', callback: function (Blueprint $table): void {
            $table->id();

            // Store al que pertenece la promoción, nullable para promociones globales
            // restrictOnDelete: no se debe eliminar un store que tenga promociones configuradas
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Nombre descriptivo de la promoción para identificación interna y frontend
            $table->string(column: 'name', length: 160);
            // Descripción detallada de la promoción, nullable si no se requiere explicación
            $table->text(column: 'description')->nullable();
            // Tipo de descuento: 'percentage', 'fixed', 'buy_x_get_y', etc.
            $table->string(column: 'discount_type', length: 50);
            // Valor del descuento según el tipo configurado
            $table->decimal(column: 'value', total: 12, places: 2);
            // Fecha de inicio de la promoción, nullable para promociones permanentes
            $table->date(column: 'starts_at')->nullable();
            // Fecha de fin de la promoción, nullable para promociones sin fecha de expiración
            $table->date(column: 'ends_at')->nullable();
            // Estado de activación: permite pausar promociones temporalmente
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            // Soft deletes para conservar historial de promociones eliminadas
            $table->softDeletes();

            $table->index(columns: ['store_id'], name: 'promotions_store_id_idx');
            $table->index(columns: ['name'], name: 'promotions_name_idx');
            $table->index(columns: ['discount_type'], name: 'promotions_discount_type_idx');
            $table->index(columns: ['starts_at'], name: 'promotions_starts_at_idx');
            $table->index(columns: ['ends_at'], name: 'promotions_ends_at_idx');
            $table->index(columns: ['is_active'], name: 'promotions_is_active_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'promotions');
    }
};
