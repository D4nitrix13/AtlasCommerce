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
     * Crea la tabla 'sales_channels' que representa los canales de venta disponibles
     * para cada tienda (web, POS, teléfono, redes sociales, etc.). Cada canal pertenece
     * a una tienda y tiene un tipo que identifica el medio de venta.
     */
    public function up(): void
    {
        Schema::create(table: 'sales_channels', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'name', length: 100);
            // Tipo de canal: web, pos, phone, social, marketplace, etc.
            $table->string(column: 'type', length: 50);
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();

            $table->index(columns: ['store_id'], name: 'sales_channels_store_id_idx');
            $table->index(columns: ['type'], name: 'sales_channels_type_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'sales_channels' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'sales_channels');
    }
};
