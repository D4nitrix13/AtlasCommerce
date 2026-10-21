<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula cada `product_images` con su `product_variants` para que el
 * storefront pueda filtrar la galería del PDP por acabado.
 *
 * Contexto: el seeder carga imágenes desde
 * `assets/products/{tenant}/{brand}/{product}/{variant}/*.webp` y
 * deriva `image_url` como SHA-256 del binario. El slug del variant se
 * conoce en seed-time pero NO se persiste en `product_images`. Hoy la
 * PDP muestra las 28-100+ imágenes del producto sin discriminar a qué
 * acabado pertenecen.
 *
 * Solución: nueva columna nullable `variant_id` con FK a
 * `product_variants.id` (nullOnDelete para no romper filas huérfanas
 * si en el futuro se elimina una variante). Nullable y sin cascade
 * mantiene compatibilidad con:
 *
 *   - Productos single-variant (caso 327/346 en la BD actual): todas
 *     las imágenes quedarán con `variant_id` apuntando al único variant.
 *   - Productos multi-variant sin assets en disco (4/19 en el
 *     diagnóstico de Oct 2026): quedan con `variant_id = NULL` y la
 *     galería degrada a mostrar todas las imágenes (correcto).
 *   - Filas heredadas de seeds previos: sin valor hasta que corra el
 *     comando `assets:backfill-image-variant` que las rellena.
 *
 * Indice `(product_id, variant_id)` para la query caliente del PDP
 * ("dame las imágenes de producto X con variant_id = Y"); índice
 * simple sobre `variant_id` para resolver la relación inversa sin
 * escanear todas las imágenes.
 *
 * No se aplica cascade delete: borrar una variante deja sus imágenes
 * huérfanas (variant_id = NULL) para auditoría/admin manual, en línea
 * con cómo el seeder trata el flag `is_active` de variants.
 *
 * Orden de migraciones: requiere que `product_variants` ya exista
 * (migration de 2026_06_22_032011).
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->foreignId(column: 'variant_id')
                ->nullable()
                ->after(column: 'product_id')
                ->constrained(table: 'product_variants', column: 'id')
                ->nullOnDelete();

            $table->index(
                columns: ['product_id', 'variant_id'],
                name: 'product_images_product_variant_idx',
            );
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->dropIndex(index: 'product_images_product_variant_idx');
            $table->dropForeign(foreign: ['variant_id']);
            $table->dropColumn(columns: ['variant_id']);
        });
    }
};
