<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->boolean('maintenance_mode')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['currency_id', 'is_active'], 'stores_currency_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
