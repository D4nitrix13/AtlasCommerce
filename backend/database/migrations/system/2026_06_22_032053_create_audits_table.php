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
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            $table->string('action', 80);
            $table->string('table_name', 100);
            $table->string('record_id', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('registered_at')->useCurrent();

            $table->timestamps();

            $table->index('user_id');
            $table->index('store_id');
            $table->index('action');
            $table->index('table_name');
            $table->index('record_id');
            $table->index('registered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
