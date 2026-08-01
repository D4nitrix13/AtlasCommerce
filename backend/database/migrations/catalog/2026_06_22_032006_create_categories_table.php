<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "categories" para el catálogo de productos.
     * Utiliza auto-referencia (parent_id → categories) para permitir
     * una estructura jerárquica de categorías y subcategorías
     * ilimitada. Cuando se elimina una categoría padre, las hijas
     * pasan a ser categorías raíz (nullOnDelete).
     */
    public function up(): void
    {
        Schema::create(table: 'categories', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Auto-referencia: permite jerarquía de categorías y subcategorías
            $table->foreignId(column: 'parent_id')
                ->nullable()
                ->constrained(table: 'categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string(column: 'name', length: 120);
            $table->string(column: 'slug', length: 150)->unique();
            $table->text(column: 'description')->nullable();
            $table->string(column: 'image_path')->nullable();
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['parent_id'], name: 'categories_parent_id_idx');
            $table->index(columns: ['slug'], name: 'categories_slug_idx');
            $table->index(columns: ['is_active'], name: 'categories_is_active_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "categories" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'categories');
    }
};
