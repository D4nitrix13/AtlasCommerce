<?php

declare(strict_types=1);

use App\Models\Stores\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `store_themes` para el sistema de theming por tenant
 * (Fase 3 del roadmap multi-instance).
 *
 * Cada tienda tiene UN theme (relación 1:1 con `stores`). El theme
 * define el branding visual: logo, colores, fuentes, etc.
 *
 * El endpoint `GET /api/storefront/theme` lee de esta tabla según el
 * host de la request (via `ResolveStoreFromDomain`).
 *
 * Tabla: `store_themes` — relación 1:1 con `stores`.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create(table: 'store_themes', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'store_id')
                ->unique()
                ->constrained(table: 'stores', column: 'id')
                ->cascadeOnDelete();

            // Nombre corto de la marca (ej: "Panda Estampados" -> "Panda").
            $table->string(column: 'short_name', length: 100)->nullable();

            // Path al logo principal (relativo a /public o URL absoluta).
            $table->string(column: 'logo_path', length: 500)->nullable();

            // Path al logo del login (puede ser distinto al principal).
            $table->string(column: 'login_logo_path', length: 500)->nullable();

            // Path al favicon.
            $table->string(column: 'favicon_path', length: 500)->nullable();

            // Colores en formato hex (#RRGGBB).
            $table->string(column: 'accent_color', length: 9)->nullable();
            $table->string(column: 'accent_hover_color', length: 9)->nullable();
            $table->string(column: 'primary_color', length: 9)->nullable();

            // Email de soporte del store (visible en login, footer, etc).
            $table->string(column: 'support_email', length: 255)->nullable();

            // Teléfono y whatsapp visibles.
            $table->string(column: 'phone', length: 30)->nullable();
            $table->string(column: 'whatsapp', length: 30)->nullable();

            // Tokens adicionales del theme (fonts, spacing, etc).
            // JSON para flexibilidad total.
            $table->json(column: 'tokens')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_themes');
    }
};
