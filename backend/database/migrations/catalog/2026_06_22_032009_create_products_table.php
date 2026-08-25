<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->longText('description')->nullable();
            $table->string('sku', 120)->unique();
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('cost_cents')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active', 'published_at'], 'products_category_active_published_idx');
            $table->index('brand_id', 'products_brand_id_idx');
            $table->index('currency_id', 'products_currency_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
