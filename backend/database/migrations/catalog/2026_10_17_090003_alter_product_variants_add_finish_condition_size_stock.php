<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de acabados, condición, stock y FK a tallas a la
 * tabla `product_variants` para la Fase 7 (schema rich de productos).
 *
 * El modelo de negocio real (La Voz Instrumentos y otros tenants que
 * venden productos con variantes visuales/condición) requiere:
 *
 *   - `finish_slug` (string 64 nullable): identificador URL-safe del
 *     acabado (ej. `rojo`, `negro`, `azul`, `sunburst`, `natural`).
 *     Nullable porque las variantes existentes (guitarras sin
 *     distinción de acabado) no lo setean.
 *   - `finish_label` (string 120 nullable): versión legible para UI
 *     (ej. `Rojo Vintage`, `Negro Onix`).
 *   - `finish_hex` (string 9 nullable): hex color con alpha opcional
 *     para swatches (formato `#RRGGBB` o `#RRGGBBAA`).
 *   - `condition` (string 16 default 'nuevo'): estado de la unidad.
 *     Valores esperados: `nuevo`, `usado`, `ambos`. La convención del
 *     proyecto usa `string` con default en lugar de `->enum()` para
 *     mantener coherencia con el resto del schema (PostgreSQL +
 *     flexibilidad para agregar valores sin DDL).
 *   - `stock` (unsignedInteger default 0): unidades disponibles para
 *     esta combinación (producto + finish + condition + size).
 *   - `size_id` (foreignId nullable → `product_sizes.id` con
 *     `nullOnDelete`): talla opcional de la variante (violín 4/4 vs
 *     3/4). Nullable porque la mayoría de variantes no tienen tallas.
 *
 * Indice compuesto `(product_id, finish_slug, condition)` optimiza el
 * lookup del storefront: "dame todas las variantes del producto X con
 * acabado Y en condición Z" (la query más frecuente en PDP).
 *
 * Defaults seguros: cualquier variante existente recibe
 * `condition='nuevo'`, `stock=0`, `finish_*=null`, `size_id=null`.
 * No rompe reviews, images, ni índices pre-existentes.
 *
 * Orden de migraciones: requiere que `product_sizes` ya exista
 * (migration `2026_10_17_090001_create_product_sizes_table` corre
 * primero).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'product_variants', callback: function (Blueprint $table): void {
            $table->string(column: 'finish_slug', length: 64)
                ->nullable()
                ->after(column: 'name');

            $table->string(column: 'finish_label', length: 120)
                ->nullable()
                ->after(column: 'finish_slug');

            // #RRGGBB o #RRGGBBAA (9 chars).
            $table->string(column: 'finish_hex', length: 9)
                ->nullable()
                ->after(column: 'finish_label');

            $table->string(column: 'condition', length: 16)
                ->default(value: 'nuevo')
                ->after(column: 'finish_hex');

            $table->unsignedInteger(column: 'stock')
                ->default(value: 0)
                ->after(column: 'condition');

            $table->foreignId(column: 'size_id')
                ->nullable()
                ->after(column: 'stock')
                ->constrained(table: 'product_sizes', column: 'id')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Lookup frecuente en storefront:
            // "variantes del producto X con acabado Y en condición Z".
            $table->index(
                columns: ['product_id', 'finish_slug', 'condition'],
                name: 'product_variants_product_finish_condition_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'product_variants', callback: function (Blueprint $table): void {
            $table->dropIndex(index: 'product_variants_product_finish_condition_idx');

            $table->dropForeign(columns: ['size_id']);

            $table->dropColumn(columns: [
                'finish_slug',
                'finish_label',
                'finish_hex',
                'condition',
                'stock',
                'size_id',
            ]);
        });
    }
};
