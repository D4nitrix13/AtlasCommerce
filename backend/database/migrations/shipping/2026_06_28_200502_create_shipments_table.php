<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
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
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->string('tracking_code', 160)->nullable()->unique();
            $table->string('tracking_url')->nullable();
            $table->string('last_event_status', 80)->nullable();
            $table->string('last_event_location')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->unsignedBigInteger('shipping_cost_cents')->default(0);
            $table->string('status', 50)->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status'], 'shipments_order_status_idx');
            $table->index(['status', 'shipped_at'], 'shipments_status_shipped_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
