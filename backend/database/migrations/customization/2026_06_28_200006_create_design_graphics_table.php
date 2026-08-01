<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena gráficos y elementos decorativos predefinidos
     * que los usuarios pueden agregar a sus diseños personalizados.
     */
    public function up(): void
    {
        Schema::create(table: 'design_graphics', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Nombre descriptivo del gráfico
            $table->string(column: 'name');

            // Categoría del gráfico (ej: "naturaleza", "geometría", "festivo")
            $table->string(column: 'category')->nullable()->index();

            // Etiquetas para búsqueda y filtrado
            $table->json(column: 'tags')->nullable();

            // Tipo de gráfico: 'svg', 'png', 'vector', etc.
            $table->string(column: 'type', length: 40)->index();

            // URL del archivo del gráfico
            $table->string(column: 'file_url');

            // URL de la imagen de vista previa (thumbnail)
            $table->string(column: 'preview_url')->nullable();

            // Tipo de licencia: 'free', 'premium', 'commercial'
            $table->string(column: 'license')->nullable();

            // Indica si el gráfico es gratuito
            $table->boolean(column: 'free')->default(value: true)->index();

            // Estado de disponibilidad del gráfico
            $table->boolean(column: 'active')->default(value: true)->index();

            $table->timestamps();

            // Índices compuestos para filtrar gráficos por categoría/tipo y estado
            $table->index(columns: ['category', 'active'], name: 'design_graphics_category_active_idx');
            $table->index(columns: ['type', 'active'], name: 'design_graphics_type_active_idx');
        });
    }

    /**
     * Elimina la tabla de gráficos de diseño.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'design_graphics');
    }
};
