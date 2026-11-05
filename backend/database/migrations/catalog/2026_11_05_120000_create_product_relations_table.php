<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciones curadas entre productos del catálogo.
 *
 * Alimentan dos rieles de la PDP: `bought_together` (Frecuentemente
 * Comprados Juntos) y `you_may_also_like` (También Te Puede Gustar).
 * El catálogo es chico y las parejas se siembran a mano
 * (`ProductRelationsSeeder`), no se calculan desde órdenes.
 *
 * La unicidad es por (origen, destino, tipo): el mismo par puede
 * convivir en los dos rieles con posiciones distintas.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create('product_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('related_product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(
                columns: ['product_id', 'related_product_id', 'type'],
                name: 'product_relations_origin_target_type_unique',
            );
            $table->index(['product_id', 'type'], name: 'product_relations_origin_type_index');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_relations');
    }
};
