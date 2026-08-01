<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->uuid('batch_uuid')->index();
            $table->string('code_hash');

            $table->timestamp('used_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['user_id', 'code_hash'], 'recovery_user_code_unique');
            $table->index(['user_id', 'used_at'], 'recovery_user_used_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_codes');
    }
};
