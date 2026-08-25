<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();

            $table->index('return_request_id', 'return_request_items_return_request_id_idx');
            $table->index('product_id', 'return_request_items_product_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
