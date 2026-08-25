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
     * Crea la tabla pivote "attribute_product" que relaciona productos
     * con sus atributos. Permite asignar valores flexibles a cada
     * producto (ej: color = "Rojo", material = "Algodón").
     * La restricción UNIQUE compuesta garantiza que un atributo solo
     * pueda asignarse una vez por producto.
     */
    public function up(): void
    {
        Schema::create(table: 'attribute_product', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId(column: 'attribute_id')
                ->constrained(table: 'attributes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Valor del atributo para este producto (flexible: text, JSON, etc.)
            $table->string(column: 'value', length: 255)->nullable();

            $table->timestamps();

            // Un atributo solo puede existir una vez por producto
            $table->unique(columns: ['product_id', 'attribute_id'], name: 'attribute_product_product_id_attribute_id_unique');
            $table->index(columns: ['product_id'], name: 'attribute_product_product_id_idx');
            $table->index(columns: ['attribute_id'], name: 'attribute_product_attribute_id_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "attribute_product" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'attribute_product');
    }
};
