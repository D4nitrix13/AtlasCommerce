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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->useCurrent();

            $table->timestamp('password_changed_at')->nullable()->index();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->index();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role_id');
            $table->index('email');
            $table->index('is_active');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
