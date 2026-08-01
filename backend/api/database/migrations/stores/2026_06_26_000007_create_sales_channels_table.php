<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 100);
            $table->string('type', 50);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
