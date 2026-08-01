<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla 'store_social_links' que almacena los enlaces a redes sociales
     * de cada tienda. Permite múltiples plataformas (Facebook, Instagram, TikTok, etc.)
     * por tienda, con su URL correspondiente y estado de actividad.
     */
    public function up(): void
    {
        Schema::create(table: 'store_social_links', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Nombre de la plataforma: facebook, instagram, tiktok, twitter, youtube, etc.
            $table->string(column: 'platform', length: 50);
            $table->string(column: 'url', length: 255);
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();

            $table->index(columns: ['store_id'], name: 'store_social_links_store_id_idx');
            $table->index(columns: ['platform'], name: 'store_social_links_platform_idx');
        });
    }

    /**
     * Revertir las migraciones.
     *
     * Elimina la tabla 'store_social_links' y todas sus restricciones de integridad referencial.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'store_social_links');
    }
};
