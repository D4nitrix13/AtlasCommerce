<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El acabado deja de ser única dimensión de la variante.
 *
 * El índice `product_variants_product_finish_unique` nació cuando la
 * única dimensión de compra era el acabado (guitarras de La Voz). Con
 * talla/capacidad como variante (opción A confirmada: la taza de 11 oz
 * y la de 15 oz son variantes distintas del mismo producto) ese índice
 * impide crear la segunda variante y el seeding fallaba por unique
 * violation.
 *
 * `NULLS NOT DISTINCT` (Postgres 15+) es deliberado: los productos sin
 * tallas llevan `size_id` NULL, y sin esta cláusula Postgres considera
 * cada NULL distinto — dos variantes sin talla con el mismo acabado
 * colarían.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'product_variants_product_finish_unique';

    private const NEW_INDEX = 'product_variants_product_finish_size_unique';

    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::OLD_INDEX);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::NEW_INDEX
            .' ON product_variants (product_id, finish_slug, size_id) NULLS NOT DISTINCT'
        );
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::NEW_INDEX);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::OLD_INDEX
            .' ON product_variants (product_id, finish_slug)'
        );
    }
};
