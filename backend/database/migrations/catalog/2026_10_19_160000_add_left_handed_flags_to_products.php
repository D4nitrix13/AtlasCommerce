<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration — agregar flags de orientación y exclusividad a productos.
 *
 *   products.is_left_handed  BOOL  -- el modelo entero es zurdo (handle tiene "left-handed")
 *   product_variants.handedness  VARCHAR(16) NULL  -- 'left-handed' o NULL (regular)
 *
 * Detección desde el filesystem del backup:
 *   - `assets/products/instrumentos-musicales-la-voz/{brand}/{product}/`:
 *     si product handle contiene "left-handed" → products.is_left_handed = true
 *   - Dentro del variant slug: si termina en "-left-handed" → variants.handedness = 'left-handed'
 *
 * Sources del campo:
 *   1. Nombre del directorio/variant (no necesita migration pre-existente)
 *   2. product.json del backup (no todos los productos lo tienen — depende de metadata_status)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean(column: 'is_left_handed')
                ->default(value: false)
                ->after(column: 'is_exclusive');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string(column: 'handedness', length: 16)
                ->nullable()
                ->after(column: 'condition');
        });

        // Index para queries que filtren por orientación (e.g. PDP "mostrar
        // solo los left-handed disponibles")
        \Illuminate\Support\Facades\DB::statement(
            'CREATE INDEX product_variants_handedness_idx ON product_variants (handedness) WHERE handedness IS NOT NULL'
        );
        \Illuminate\Support\Facades\DB::statement(
            'CREATE INDEX products_is_left_handed_idx ON products (is_left_handed) WHERE is_left_handed = true'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS products_is_left_handed_idx');
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS product_variants_handedness_idx');

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(columns: 'handedness');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(columns: 'is_left_handed');
        });
    }
};
