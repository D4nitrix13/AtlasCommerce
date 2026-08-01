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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->string('code', 80)->unique();
            $table->string('discount_type', 50);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_amount', 12, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('code');
            $table->index('discount_type');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
