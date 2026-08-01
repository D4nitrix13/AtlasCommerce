<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_mockup_renders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('custom_design_id')
                ->constrained('custom_designs')
                ->cascadeOnDelete();

            $table->foreignId('product_mockup_id')
                ->constrained('product_mockups')
                ->cascadeOnDelete();

            $table->string('view', 40)->index();
            $table->string('rendered_image_url');
            $table->string('status', 40)->default('ready')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('rendered_at')->nullable()->index();

            $table->timestamps();

            $table->unique(
                ['custom_design_id', 'product_mockup_id', 'view'],
                'design_render_unique'
            );
            $table->index(['custom_design_id', 'status'], 'design_render_design_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_mockup_renders');
    }
};
