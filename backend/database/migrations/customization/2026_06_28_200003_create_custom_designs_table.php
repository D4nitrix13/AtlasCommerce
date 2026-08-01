<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_designs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->string('name')->nullable();
            $table->string('status', 40)->default('draft')->index();

            $table->string('preview_url')->nullable();
            $table->string('production_file_url')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status'], 'custom_designs_customer_status_idx');
            $table->index(['user_id', 'status'], 'custom_designs_user_status_idx');
            $table->index(['product_id', 'product_variant_id'], 'custom_designs_product_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_designs');
    }
};
