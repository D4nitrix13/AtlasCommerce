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
        Schema::create('two_factor_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);
            $table->enum('preferred_method', ['email_otp', 'totp', 'passkey'])->nullable();
            $table->boolean('required')->default(false);
            $table->timestamp('enabled_at')->nullable();

            $table->timestamps();

            $table->index('enabled');
            $table->index('preferred_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_settings');
    }
};
