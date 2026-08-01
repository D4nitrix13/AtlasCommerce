<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_customizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->boolean('customizable')->default(true)->index();
            $table->string('customization_type', 60)->default('print')->index();
            $table->string('editor_type', 60)->default('canvas')->index();
            $table->string('status', 40)->default('active')->index();
            $table->text('instructions')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'product_variant_id'], 'custom_product_variant_idx');
            $table->index(['status', 'customizable'], 'custom_status_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_customizations');
    }
};
