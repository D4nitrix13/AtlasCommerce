<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena los renders pre-generados de diseños personalizados
     * superpuestos sobre mockups de producto. Cada render es una imagen final
     * lista para vista previa o producción.
     */
    public function up(): void
    {
        Schema::create(table: 'design_mockup_renders', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Diseño personalizado que se renderizó
            $table->foreignId(column: 'custom_design_id')
                ->constrained(table: 'custom_designs')
                ->cascadeOnDelete();

            // Mockup de producto sobre el que se superpuso el diseño
            $table->foreignId(column: 'product_mockup_id')
                ->constrained(table: 'product_mockups')
                ->cascadeOnDelete();

            // Vista del mockup renderizado (frontal, trasera, etc.)
            $table->string(column: 'view', length: 40)->index();

            // URL de la imagen renderizada resultante
            $table->string(column: 'rendered_image_url');

            // Estado del render: 'pending', 'processing', 'ready', 'failed'
            $table->string(column: 'status', length: 40)->default(value: 'ready')->index();

            // Metadatos del render en JSON (dimensiones, tiempo de procesamiento, etc.)
            $table->json(column: 'metadata')->nullable();

            // Fecha y hora en que se completó el render
            $table->timestamp(column: 'rendered_at')->nullable()->index();

            $table->timestamps();

            // Unicidad: cada combinación diseño+mockup+view genera un solo render
            $table->unique(
                columns: ['custom_design_id', 'product_mockup_id', 'view'],
                name: 'design_render_unique'
            );
            // Índice para buscar renders por diseño y estado
            $table->index(columns: ['custom_design_id', 'status'], name: 'design_render_design_status_idx');
        });
    }

    /**
     * Elimina la tabla de renders de mockup de diseño.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'design_mockup_renders');
    }
};
