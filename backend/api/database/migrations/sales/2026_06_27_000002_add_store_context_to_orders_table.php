<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->foreignId('sales_channel_id')
                ->nullable()
                ->constrained('sales_channels')
                ->nullOnDelete();

            $table->foreignId('user_creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index('store_id');
            $table->index('sales_channel_id');
            $table->index('user_creator_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['sales_channel_id']);
            $table->dropForeign(['user_creator_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['sales_channel_id']);
            $table->dropIndex(['user_creator_id']);
            $table->dropColumn(['store_id', 'sales_channel_id', 'user_creator_id']);
        });
    }
};
