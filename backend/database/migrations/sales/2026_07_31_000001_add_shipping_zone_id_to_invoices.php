<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 10: persistir la zona de envío en la invoice para que el
     * ShipmentService pueda crear el envío con la misma zona que usó
     * el cliente en el checkout.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('shipping_zone_id')
                ->nullable()
                ->after('shipping_cents')
                ->constrained('shipping_zones')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('shipping_zone_id', 'invoices_shipping_zone_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_shipping_zone_id_idx');
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn('shipping_zone_id');
        });
    }
};
