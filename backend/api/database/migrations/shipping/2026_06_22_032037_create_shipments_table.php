<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('shipping_company_id')
                ->nullable()
                ->constrained('shipping_companies')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('shipping_zone_id')
                ->nullable()
                ->constrained('shipping_zones')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('tracking_code', 160)->nullable()->unique();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('status', 50)->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('shipping_company_id');
            $table->index('shipping_zone_id');
            $table->index('tracking_code');
            $table->index('status');
            $table->index('shipped_at');
            $table->index('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
