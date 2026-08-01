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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('type', 50)->default('text');
            $table->text('description')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);

            $table->timestamps();

            $table->index('slug');
            $table->index('type');
            $table->index('is_filterable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};