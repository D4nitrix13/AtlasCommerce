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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_id')
                ->constrained('inventories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('movement_type', 50);
            $table->unsignedInteger('quantity');

            $table->unsignedInteger('previous_stock')->default(0);
            $table->unsignedInteger('new_stock')->default(0);

            $table->text('reason')->nullable();
            $table->timestamp('movement_date')->useCurrent();

            $table->timestamps();

            $table->index('inventory_id');
            $table->index('user_id');
            $table->index('movement_type');
            $table->index('movement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
