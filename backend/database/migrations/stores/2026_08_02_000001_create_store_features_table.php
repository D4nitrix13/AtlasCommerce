<?php

declare(strict_types=1);

use App\Models\Stores\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `store_features` para el sistema de feature flags
 * por tenant.
 *
 * Cada registro representa una feature (wholesale_pricing, bulk_import,
 * etc.) habilitada o deshabilitada para una tienda específica. La
 * combinación `(store_id, slug)` es única.
 *
 * El helper `Store::hasFeature(slug)` consulta esta tabla para
 * resolver el flag en runtime. El comando `tenants:bootstrap` puebla
 * la tabla desde el campo `features` del JSON de seed.
 *
 * Tabla: `store_features` — relación N:1 con `stores`.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'store_features', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores', column: 'id')
                ->cascadeOnDelete();

            // Slug de la feature (ej: 'wholesale_pricing', 'bulk_import').
            // Usar kebab-case, maximo 100 chars.
            $table->string(column: 'slug', length: 100);

            // Si la feature esta activa o no. Default true para que
            // nuevas features se activen por default.
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();

            // Una feature por (tienda, slug).
            $table->unique(columns: ['store_id', 'slug'], name: 'store_features_store_id_slug_unique');

            // Indice por slug para queries globales
            // (ej: "que tiendas tienen wholesale_pricing?").
            $table->index(columns: ['slug'], name: 'store_features_slug_idx');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_features');
    }
};
