<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_mockups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_customization_id')
                ->constrained('product_customizations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('view', 40)->index();
            $table->string('mockup_type', 60)->default('flat')->index();
            $table->string('base_image_url');
            $table->string('mask_image_url')->nullable();
            $table->string('shadow_image_url')->nullable();
            $table->json('render_settings')->nullable();
            $table->boolean('primary')->default(false)->index();
            $table->boolean('active')->default(true)->index();

            $table->timestamps();

            $table->index(['product_customization_id', 'view'], 'mockup_custom_view_idx');
            $table->index(['product_customization_id', 'primary'], 'mockup_custom_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mockups');
    }
};
