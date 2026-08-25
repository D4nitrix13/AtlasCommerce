<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "attributes" para definir los atributos del catálogo
     * (ej: color, tamaño, material). El campo "type" indica el tipo
     * de dato del atributo (text, number, select, etc.) y "is_filterable"
     * determina si se puede usar como filtro de búsqueda en la tienda.
     */
    public function up(): void
    {
        Schema::create(table: 'attributes', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->string(column: 'name', length: 100);
            $table->string(column: 'slug', length: 120)->unique();
            $table->string(column: 'type', length: 50)->default(value: 'text');
            $table->text(column: 'description')->nullable();
            $table->boolean(column: 'is_filterable')->default(value: false);
            $table->boolean(column: 'is_required')->default(value: false);

            $table->timestamps();

            $table->index(columns: ['slug'], name: 'attributes_slug_idx');
            $table->index(columns: ['type'], name: 'attributes_type_idx');
            $table->index(columns: ['is_filterable'], name: 'attributes_is_filterable_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "attributes" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'attributes');
    }
};
