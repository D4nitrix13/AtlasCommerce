<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkey_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('credential_id', 1024)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->jsonb('transports')->nullable();
            $table->string('aaguid', 100)->nullable();
            $table->string('device_name', 100)->nullable();

            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backup_state')->default(false);

            $table->timestamp('last_used_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('revoked_at')->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'active'], 'passkeys_user_active_idx');
            $table->index(['user_id', 'revoked_at'], 'passkeys_user_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_credentials');
    }
};
