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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('address_type', 50)->default('shipping');
            $table->string('country', 80)->default('Nicaragua');
            $table->string('department', 100)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->text('detailed_address');
            $table->text('reference')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('address_type');
            $table->index('country');
            $table->index('department');
            $table->index('municipality');
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
