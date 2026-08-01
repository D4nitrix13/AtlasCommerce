<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('custom_design_id')
                ->constrained('custom_designs')
                ->cascadeOnDelete();

            $table->foreignId('print_area_id')
                ->constrained('print_areas')
                ->restrictOnDelete();

            $table->foreignId('design_asset_id')
                ->nullable()
                ->constrained('design_assets')
                ->nullOnDelete();

            $table->foreignId('design_font_id')
                ->nullable()
                ->constrained('design_fonts')
                ->nullOnDelete();

            $table->foreignId('design_graphic_id')
                ->nullable()
                ->constrained('design_graphics')
                ->nullOnDelete();

            $table->string('layer_type', 40)->index();
            $table->string('name')->nullable();
            $table->text('text_content')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('background_color', 20)->nullable();
            $table->string('alignment', 30)->nullable();

            $table->decimal('position_x', 12, 4)->default(0);
            $table->decimal('position_y', 12, 4)->default(0);
            $table->decimal('width', 12, 4)->nullable();
            $table->decimal('height', 12, 4)->nullable();
            $table->decimal('scale_x', 10, 4)->default(1);
            $table->decimal('scale_y', 10, 4)->default(1);
            $table->decimal('rotation', 8, 3)->default(0);
            $table->decimal('opacity', 5, 4)->default(1);

            $table->integer('z_index')->default(0)->index();
            $table->boolean('locked')->default(false);
            $table->boolean('visible')->default(true)->index();

            $table->json('styles')->nullable();
            $table->json('effects')->nullable();

            $table->timestamps();

            $table->index(['custom_design_id', 'print_area_id', 'z_index'], 'design_layers_design_area_z_idx');
            $table->index(['print_area_id', 'layer_type'], 'design_layers_area_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_layers');
    }
};
