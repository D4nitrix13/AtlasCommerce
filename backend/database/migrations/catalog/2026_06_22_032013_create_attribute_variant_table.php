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
     * Crea la tabla pivote "attribute_variant" que relaciona variantes
     * de producto con sus atributos. Permite asignar valores específicos
     * a cada variante (ej: variante "Rojo-M" → color = "Rojo", talla = "M").
     * La restricción UNIQUE compuesta garantiza que un atributo solo
     * pueda asignarse una vez por variante.
     */
    public function up(): void
    {
        Schema::create(table: 'attribute_variant', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'product_variant_id')
                ->constrained(table: 'product_variants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'attribute_id')
                ->constrained(table: 'attributes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Valor del atributo para esta variante (flexible: text, JSON, etc.)
            $table->string(column: 'value', length: 255)->nullable();

            $table->timestamps();

            // Un atributo solo puede existir una vez por variante
            $table->unique(columns: ['product_variant_id', 'attribute_id'], name: 'attribute_variant_product_variant_id_attribute_id_unique');
            $table->index(columns: ['product_variant_id'], name: 'attribute_variant_product_variant_id_idx');
            $table->index(columns: ['attribute_id'], name: 'attribute_variant_attribute_id_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "attribute_variant" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'attribute_variant');
    }
};
