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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->string('status', 50)->default('active');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('store_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
