<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('reviewed_at')->useCurrent();

            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('customer_id');
            $table->index('store_id');
            $table->index('rating');
            $table->index('is_approved');
            $table->index('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
