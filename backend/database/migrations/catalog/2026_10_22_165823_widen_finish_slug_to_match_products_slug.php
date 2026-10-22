<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensancha `product_variants.finish_slug` de 64 a 180 caracteres para que
 * coincida con `products.slug`, de donde se copia.
 *
 * POR QUÉ
 *
 * `finish_slug` se puebla copiando el slug del directorio de variante, y
 * cuando el producto tiene una sola variante ese directorio ES el
 * directorio del producto. O sea que el valor es, literalmente, una copia
 * de `products.slug`, que es `varchar(180)`.
 *
 * Con la columna en 64 el INSERT revienta con `SQLSTATE[22001] value too
 * long` para todo producto cuyo slug pase de 64 caracteres, y como el
 * error aborta la transacción completa, no se crea ni la variante ni las
 * imágenes: el producto queda sin galería. Se midieron 10 abortos de ese
 * tipo en el árbol del tenant, más 5 SKUs duplicados por encima de los 120.
 *
 * La alternativa era escribir `NULL` cuando el slug se espeja. Ya se
 * probó y NO sirve: `StorefrontProductResource::resolveVariants()` filtra
 * las variantes sin `finish_slug` (el PDP devolvería `variants: []`),
 * `BackfillVariantFinishCommand` usa "finish_slug !== products.slug"
 * como gate para considerar que la variante ya tiene acabado real (con
 * `NULL` el gate nunca pasa y el backfill queda neutralizado
 * justamente para las filas que debería arreglar), y
 * `BackfillImageVariantCommand` indexa por `finish_slug` y contaría
 * esas imágenes como huérfanas.
 *
 * Es ensanchar, no truncar: truncar el slug produce identificadores que
 * colisionan entre sí y que nadie sabe después qué producto representan.
 *
 * El índice compuesto `(product_id, finish_slug, condition)` sobrevive
 * sin cambios: 180 caracteres muy lejos del límite de tupla de un índice
 * btree de PostgreSQL (~2704 bytes).
 */
return new class extends Migration
{
    /** Longitud final: la misma que `products.slug`. */
    private const FINISH_SLUG_LENGTH = 180;

    public function up(): void
    {
        // Alterar una columna que participa de un índice en PostgreSQL
        // reconstruye el índice automáticamente; no hace falta dropearlo.
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string(column: 'finish_slug', length: self::FINISH_SLUG_LENGTH)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Sólo se revierte si ningún valor excede 64; si los hay, fallar
        // es preferible a truncar en silencio.
        $tooLong = DB::table('product_variants')
            ->whereNotNull('finish_slug')
            ->whereRaw('length(finish_slug) > ?', [64])
            ->count();

        if ($tooLong > 0) {
            throw new RuntimeException(
                "No se puede encoger finish_slug a 64: {$tooLong} variante(s) tienen un valor más largo. "
                .'Truncar produciría identificadores colisionantes; resolver a mano antes de revertir.',
            );
        }

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string(column: 'finish_slug', length: 64)->nullable()->change();
        });
    }
};
