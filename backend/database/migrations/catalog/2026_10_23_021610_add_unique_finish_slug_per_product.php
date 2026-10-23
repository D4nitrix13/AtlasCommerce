<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unicidad de acabado por producto, respaldada por la base.
 *
 * POR QUÉ
 *
 * Desde que la API admin puede crear variantes, la regla "un producto no
 * tiene dos variantes con el mismo acabado" la garantiza la capa de
 * validación, no la base. Eso deja dos agujeros:
 *
 *  - Un INSERT por SQL directo, tinker o un futuro importador puede colgar
 *    dos variantes del mismo acabado del mismo producto sin que nada lo
 *    note. Con `finish_slug` repetido, el selector de acabados del PDP
 *    ofrece dos opciones que abren exactamente la misma galería.
 *  - Dos peticiones concurrentes que validan antes de insertar pasan
 *    ambas, y la segunda viola la regla: validar y después insertar no es
 *    atómico.
 *
 * La unicidad GLOBAL sería incorrecta por dominio: una SG Standard y una
 * Les Paul pueden ser ambas `ebony`. El alcance correcto es por producto,
 * que es como consulta el PDP.
 *
 * POR QUÉ ES PARCIAL
 *
 * `finish_slug` es nullable y `deleted_at` hace soft delete. Un índice
 * único sin predicado no expresa ninguna de las dos cosas:
 *
 *  - Un producto con dos variantes sin acabado (capo, funda) empuja `NULL`
 *    dos veces. PostgreSQL lo toleraría en un índice único normal, pero
 *    el índice acabaría guardando filas que no expresan la regla.
 *  - Las variantes soft-deleted no deberían bloquear volver a crear el
 *    mismo acabado: el catálogo las conserva y la auditoría las cuenta.
 *
 * Sin `CONCURRENTLY` a propósito: `CREATE INDEX CONCURRENTLY` no puede
 * ejecutarse dentro de la transacción en la que Laravel envuelve la
 * migración, y sobre 421 filas el bloqueo es despreciable.
 *
 * Verificado antes de aplicar: 0 pares `(product_id, finish_slug)`
 * duplicados en el árbol, así que el índice se crea sin conflicto.
 */
return new class extends Migration
{
    private const INDEX = 'product_variants_product_finish_unique';

    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX product_variants_product_finish_unique
                ON product_variants (product_id, finish_slug)
                WHERE finish_slug IS NOT NULL AND deleted_at IS NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
