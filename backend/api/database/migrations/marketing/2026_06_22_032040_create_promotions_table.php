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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('discount_type', 50);
            $table->decimal('value', 12, 2);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('name');
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
        Schema::dropIfExists('promotions');
    }
};
