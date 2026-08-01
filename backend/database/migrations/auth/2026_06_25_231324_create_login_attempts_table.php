<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email')->index();
            $table->boolean('successful')->default(false)->index();
            $table->string('failure_reason')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('suspicious')->default(false)->index();
            $table->timestamp('attempted_at')->index();

            $table->timestamps();

            $table->index(['email', 'attempted_at'], 'login_attempts_email_time_idx');
            $table->index(['ip', 'attempted_at'], 'login_attempts_ip_time_idx');
            $table->index(['user_id', 'successful'], 'login_attempts_user_success_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
