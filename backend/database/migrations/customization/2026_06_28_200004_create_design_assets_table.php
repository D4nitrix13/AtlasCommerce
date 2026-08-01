<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->string('original_name');
            $table->string('file_name');
            $table->string('mime_type', 120)->index();
            $table->string('extension', 20)->index();
            $table->string('storage_disk')->default('public');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes');

            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->unsignedSmallInteger('dpi')->nullable();

            $table->string('checksum', 128)->nullable()->index();
            $table->string('source', 40)->default('upload')->index();
            $table->string('status', 40)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status'], 'design_assets_customer_status_idx');
            $table->index(['user_id', 'status'], 'design_assets_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_assets');
    }
};
