<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que define las opciones de personalización disponibles para cada producto.
     * Permite indicar si un producto es personalizable, qué tipo de editor se usa
     * y el estado actual de la personalización.
     */
    public function up(): void
    {
        Schema::create(table: 'product_customizations', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Relación con el producto; se elimina en cascada porque la personalización depende del producto
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnDelete();

            // La variante es opcional; permite personalizar variantes específicas
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->cascadeOnDelete();

            // Indica si el producto admite personalización en este momento
            $table->boolean(column: 'customizable')->default(value: true)->index();

            // Tipo de personalización: 'print', 'engrave', etc.
            $table->string(column: 'customization_type', length: 60)->default(value: 'print')->index();

            // Motor de edición: 'canvas', 'fabricjs', 'svg', etc.
            $table->string(column: 'editor_type', length: 60)->default(value: 'canvas')->index();

            // Estado de la configuración: 'active', 'inactive'
            $table->string(column: 'status', length: 40)->default(value: 'active')->index();

            // Instrucciones especiales para producción
            $table->text(column: 'instructions')->nullable();

            $table->timestamps();

            // Índice compuesto para búsquedas por producto y variante
            $table->index(columns: ['product_id', 'product_variant_id'], name: 'custom_product_variant_idx');
            // Índice compuesto para filtrar por estado y disponibilidad
            $table->index(columns: ['status', 'customizable'], name: 'custom_status_enabled_idx');
        });
    }

    /**
     * Elimina la tabla de personalizaciones de producto.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_customizations');
    }
};
