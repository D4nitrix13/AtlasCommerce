<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('fingerprint_hash');
            $table->string('device_name')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['user_id', 'fingerprint_hash'], 'trusted_user_fingerprint_unique');
            $table->index(['user_id', 'expires_at'], 'trusted_user_expires_idx');
            $table->index(['user_id', 'revoked_at'], 'trusted_user_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
