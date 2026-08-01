<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_fonts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('family')->index();
            $table->string('style', 40)->default('normal');
            $table->string('weight', 20)->default('400');
            $table->string('file_path')->nullable();
            $table->string('license')->nullable();
            $table->boolean('active')->default(true)->index();

            $table->timestamps();

            $table->unique(['family', 'style', 'weight'], 'design_fonts_family_style_weight_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_fonts');
    }
};
