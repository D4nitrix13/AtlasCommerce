<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email')->index();
            $table->string('token_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();

            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['email', 'expires_at'], 'password_reset_email_expires_idx');
            $table->index(['user_id', 'used_at'], 'password_reset_user_used_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
