<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "brands" para gestionar las marcas de productos.
     * Cada marca tiene nombre y slug únicos, con soporte para logo
     * y estado de activación.
     */
    public function up(): void
    {
        Schema::create(table: 'brands', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->string(column: 'name', length: 120)->unique();
            $table->string(column: 'slug', length: 150)->unique();
            $table->text(column: 'description')->nullable();
            $table->string(column: 'logo_url')->nullable();
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['slug'], name: 'brands_slug_idx');
            $table->index(columns: ['is_active'], name: 'brands_is_active_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "brands" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'brands');
    }
};
