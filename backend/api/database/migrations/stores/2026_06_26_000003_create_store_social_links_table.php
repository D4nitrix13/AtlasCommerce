<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_social_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('platform', 50);
            $table->string('url', 255);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_social_links');
    }
};
