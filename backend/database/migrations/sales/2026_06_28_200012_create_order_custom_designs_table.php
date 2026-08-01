<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_custom_designs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('custom_design_id')
                ->nullable()
                ->constrained('custom_designs')
                ->nullOnDelete();

            $table->json('design_snapshot');
            $table->string('production_file_url')->nullable();
            $table->string('production_status', 40)->default('pending')->index();
            $table->text('production_notes')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('production_started_at')->nullable()->index();
            $table->timestamp('production_completed_at')->nullable()->index();

            $table->timestamps();

            $table->unique('order_item_id', 'order_custom_design_order_item_unique');
            $table->index(['order_id', 'production_status'], 'order_custom_design_order_status_idx');
            $table->index(['production_status', 'approved_at'], 'order_custom_designs_status_approved_idx');
            $table->index(['reviewed_by', 'reviewed_at'], 'order_custom_designs_reviewer_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_custom_designs');
    }
};
