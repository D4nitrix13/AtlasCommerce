<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_graphics', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('type', 40)->index();
            $table->string('file_url');
            $table->string('preview_url')->nullable();
            $table->string('license')->nullable();
            $table->boolean('free')->default(true)->index();
            $table->boolean('active')->default(true)->index();

            $table->timestamps();

            $table->index(['category', 'active'], 'design_graphics_category_active_idx');
            $table->index(['type', 'active'], 'design_graphics_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_graphics');
    }
};
