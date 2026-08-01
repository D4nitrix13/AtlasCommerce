<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('sku', 120)->unique();
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->string('barcode', 120)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id', 'product_variants_product_id_idx');
            $table->index('is_active', 'product_variants_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
