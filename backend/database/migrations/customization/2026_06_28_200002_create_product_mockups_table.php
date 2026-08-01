<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena los mockups disponibles para cada personalización de producto.
     * Los mockups son imágenes base sobre las que se superponen los diseños personalizados.
     */
    public function up(): void
    {
        Schema::create(table: 'product_mockups', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cada mockup pertenece a una personalización de producto
            $table->foreignId(column: 'product_customization_id')
                ->constrained(table: 'product_customizations')
                ->cascadeOnDelete();

            // Nombre descriptivo del mockup (ej: "Camiseta frontal plansa")
            $table->string(column: 'name');

            // Vista del mockup (frontal, trasera, lateral, etc.)
            $table->string(column: 'view', length: 40)->index();

            // Tipo de mockup: 'flat' (plano), '3d', 'lifestyle', etc.
            $table->string(column: 'mockup_type', length: 60)->default(value: 'flat')->index();

            // URL de la imagen base del mockup
            $table->string(column: 'base_image_url');

            // URL de la máscara para delimitar el área de diseño (opcional)
            $table->string(column: 'mask_image_url')->nullable();

            // URL de la sombra para efecto de profundidad (opcional)
            $table->string(column: 'shadow_image_url')->nullable();

            // Configuraciones de renderizado en JSON (formatos, calidades, etc.)
            $table->json(column: 'render_settings')->nullable();

            // Indica si este es el mockup principal para mostrar por defecto
            $table->boolean(column: 'primary')->default(value: false)->index();

            // Estado de disponibilidad del mockup
            $table->boolean(column: 'active')->default(value: true)->index();

            $table->timestamps();

            // Índice para buscar mockups por personalización y vista
            $table->index(columns: ['product_customization_id', 'view'], name: 'mockup_custom_view_idx');
            // Índice para encontrar rápidamente el mockup principal de una personalización
            $table->index(columns: ['product_customization_id', 'primary'], name: 'mockup_custom_primary_idx');
        });
    }

    /**
     * Elimina la tabla de mockups de producto.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_mockups');
    }
};
