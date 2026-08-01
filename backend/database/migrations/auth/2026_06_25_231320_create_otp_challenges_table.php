<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();

            $table->uuid('challenge_uuid')->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('method', 30);
            $table->string('purpose', 60)->index();
            $table->string('code_hash');
            $table->string('destination')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->unsignedSmallInteger('resend_count')->default(0);

            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamp('locked_until')->nullable()->index();

            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'purpose', 'expires_at'], 'otp_user_purpose_expires_idx');
            $table->index(['method', 'purpose'], 'otp_method_purpose_idx');
            $table->index(['destination', 'purpose'], 'otp_destination_purpose_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
