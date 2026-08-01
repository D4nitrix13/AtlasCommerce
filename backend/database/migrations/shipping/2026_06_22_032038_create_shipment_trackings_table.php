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
        Schema::create('shipment_trackings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('status', 80);
            $table->string('location', 200)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('registered_at')->useCurrent();

            $table->timestamps();

            $table->index('shipment_id');
            $table->index('status');
            $table->index('registered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_trackings');
    }
};
