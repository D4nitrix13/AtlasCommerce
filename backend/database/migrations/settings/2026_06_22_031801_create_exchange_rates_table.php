<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_currency_id')
                ->constrained('currencies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('to_currency_id')
                ->constrained('currencies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('value', 18, 8);
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['from_currency_id', 'to_currency_id', 'rate_date'], 'exchange_rates_pair_date_unique');
            $table->index('rate_date', 'exchange_rates_rate_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
