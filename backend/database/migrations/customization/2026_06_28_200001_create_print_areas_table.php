<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_areas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_customization_id')
                ->constrained('product_customizations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 80);
            $table->string('view', 40)->index();

            $table->decimal('width_inches', 8, 2)->nullable();
            $table->decimal('height_inches', 8, 2)->nullable();
            $table->unsignedInteger('width_px');
            $table->unsignedInteger('height_px');
            $table->unsignedSmallInteger('dpi')->default(300)->index();

            $table->decimal('position_x', 10, 4)->default(0);
            $table->decimal('position_y', 10, 4)->default(0);
            $table->decimal('relative_width', 10, 4)->nullable();
            $table->decimal('relative_height', 10, 4)->nullable();

            $table->json('mask')->nullable();
            $table->json('safe_zone')->nullable();

            $table->boolean('active')->default(true)->index();

            $table->timestamps();

            $table->unique(['product_customization_id', 'code'], 'print_area_custom_code_unique');
            $table->index(['product_customization_id', 'view'], 'print_area_custom_view_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_areas');
    }
};
