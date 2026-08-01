<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de seguimiento de envíos para registrar
     * cada evento en la trayectoria de un paquete.
     */
    public function up(): void
    {
        Schema::create(table: 'shipment_trackings', callback: function (Blueprint $table): void {
            $table->id();

            // Envío al que pertenece este evento de seguimiento: cascadeOnDelete porque si se elimina el envío sus eventos no tienen sentido
            $table->foreignId(column: 'shipment_id')
                ->constrained(table: 'shipments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Estado del evento de seguimiento (ej: 'picked_up', 'in_transit', 'delivered')
            $table->string(column: 'status', length: 80);
            // Ubicación geográfica del evento (ciudad, almacén, centro de distribución)
            $table->string(column: 'location', length: 200)->nullable();
            // Descripción detallada del evento de seguimiento
            $table->text(column: 'description')->nullable();
            // Fecha y hora en que se registró el evento, se establece automáticamente
            $table->timestamp(column: 'registered_at')->useCurrent();

            $table->timestamps();

            $table->index(columns: ['shipment_id'], name: 'shipment_trackings_shipment_id_idx');
            $table->index(columns: ['status'], name: 'shipment_trackings_status_idx');
            $table->index(columns: ['registered_at'], name: 'shipment_trackings_registered_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'shipment_trackings');
    }
};
