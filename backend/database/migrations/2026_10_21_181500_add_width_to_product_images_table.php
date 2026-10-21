<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste el ancho (px) de cada `product_images`.
 *
 * Contexto: el seeder deriva `image_url` como SHA-256 del binario
 * (`tenants/{tenant}/products/{slug}/{hash12}.webp`), así que el
 * `storage_key` NO contiene ni el rol (`front`, `back`, …) ni el ancho.
 * El rol sobrevive en `sort_order` (1:1 con
 * `CatalogAssetsSeeder::ROLE_ORDER`), pero el ancho se perdía: la PDP
 * terminaba mostrando 32 slides para lo que son 8 fotos en 4
 * resoluciones, porque cada resolución era indistinguible de las otras.
 *
 * Sin `width` no hay forma honesta de elegir "la de mayor resolución":
 * adivinar produce una miniatura 400px como slide principal.
 *
 * Nullable a propósito: las filas heredadas de seeds previos quedan en
 * NULL hasta que corra `assets:backfill-image-variant`, que las rellena
 * re-derivando el `storage_key` del árbol de assets. El Resource trata
 * NULL como "no se sabe" y NO colapsa el grupo — degrada al
 * comportamiento actual en vez de mostrar una resolución arbitraria.
 *
 * Índice: ninguno. `width` no participa de ninguna query (solo de un
 * `max()` en memoria sobre imágenes ya cargadas por la relación), así
 * que un índice acá sería escritura extra sin selectivity.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->unsignedSmallInteger(column: 'width')
                ->nullable()
                ->after(column: 'sort_order');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table(table: 'product_images', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: ['width']);
        });
    }
};
