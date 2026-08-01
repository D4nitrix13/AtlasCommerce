<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('method', ['email_otp'])->default('email_otp');

            $table->enum('purpose', [
                'login_2fa',
                'enable_2fa',
                'change_2fa',
            ])->default('login_2fa');

            $table->text('code_hash');
            $table->string('destination')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);

            $table->boolean('used')->default(false);

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->ipAddress('request_ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'used']);
            $table->index(['user_id', 'expires_at']);
            $table->index(['method', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
