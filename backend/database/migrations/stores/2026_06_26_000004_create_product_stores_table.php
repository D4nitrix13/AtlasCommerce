<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('store_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('slug')->nullable();
            $table->decimal('public_price', 12, 2)->nullable();
            $table->text('public_description')->nullable();

            $table->boolean('is_visible')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['product_id', 'store_id'], 'product_stores_product_store_unique');
            $table->unique(['store_id', 'slug'], 'product_stores_store_slug_unique');

            $table->index(['store_id', 'is_visible'], 'product_stores_store_visible_idx');
            $table->index(['store_id', 'is_visible', 'is_featured'], 'product_stores_visible_featured_idx');
            $table->index(['store_id', 'sort_order'], 'product_stores_store_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stores');
    }
};
