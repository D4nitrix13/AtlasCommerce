<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->decimal('exchange_rate_snapshot', 18, 8);
            $table->string('status', 50)->default('pending');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'refunded_at'], 'refunds_status_date_idx');
            $table->index('return_request_id', 'refunds_return_request_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
