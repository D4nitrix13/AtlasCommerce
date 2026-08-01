<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'product_id'], 'category_product_unique');
        });

        // Backfill: poblar la pivot con la categoría principal de cada producto.
        // `products.category_id` se mantiene como "categoría principal" para
        // queries del storefront; la pivot habilita la asignación M:N.
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
            DB::table('category_product')->insertUsing(
                ['category_id', 'product_id', 'created_at', 'updated_at'],
                DB::table('products')
                    ->select('category_id', 'id', 'created_at', 'updated_at')
                    ->whereNotNull('category_id'),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
