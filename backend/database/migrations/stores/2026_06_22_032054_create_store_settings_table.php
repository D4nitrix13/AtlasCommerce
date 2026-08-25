<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Tabla EAV flexible para configuración por tienda. Cada registro es una
     * clave-valor vinculada a una tienda, con tipo de valor y visibilidad pública.
     */
    public function up(): void
    {
        Schema::create(table: 'store_settings', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // FK: cada setting pertenece a una tienda
            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'setting_group', length: 80)->default(value: 'general');
            $table->string(column: 'setting_key', length: 120);
            $table->text(column: 'setting_value')->nullable();
            $table->string(column: 'value_type', length: 20)->default(value: 'string');
            $table->string(column: 'description', length: 255)->nullable();
            $table->boolean(column: 'is_public')->default(value: false);

            $table->timestamps();

            // Un solo valor por clave dentro de cada tienda
            $table->unique(columns: ['store_id', 'setting_key'], name: 'store_settings_store_key_unique');

            $table->index(columns: ['store_id'], name: 'store_settings_store_id_idx');
            $table->index(columns: ['setting_group'], name: 'store_settings_group_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_settings');
    }
};
