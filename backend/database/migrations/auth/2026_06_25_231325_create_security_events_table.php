<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email')->nullable()->index();
            $table->string('event_type', 80)->index();
            $table->string('severity', 30)->default('info')->index();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('event_at')->index();

            $table->timestamps();

            $table->index(['user_id', 'event_type'], 'security_user_event_idx');
            $table->index(['event_type', 'event_at'], 'security_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
