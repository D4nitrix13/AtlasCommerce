<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('logo')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();

            $table->string('primary_currency', 10)->default('NIO');
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();

            $table->boolean('maintenance_mode')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['currency_id', 'is_active'], 'stores_currency_active_idx');
            $table->index(['is_active', 'maintenance_mode'], 'stores_active_maintenance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
