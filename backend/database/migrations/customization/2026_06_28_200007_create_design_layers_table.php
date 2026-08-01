<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena las capas de un diseño personalizado.
     * Cada capa representa un elemento visual (texto, imagen, gráfico) con su
     * posición, tamaño, rotación y estilos independientes.
     */
    public function up(): void
    {
        Schema::create(table: 'design_layers', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Diseño al que pertenece la capa; se elimina en cascada
            $table->foreignId(column: 'custom_design_id')
                ->constrained(table: 'custom_designs')
                ->cascadeOnDelete();

            // Área de impresión donde se ubica la capa
            $table->foreignId(column: 'print_area_id')
                ->constrained(table: 'print_areas')
                ->restrictOnDelete();

            // Asset multimedia asociado a la capa (opcional, para capas de imagen)
            $table->foreignId(column: 'design_asset_id')
                ->nullable()
                ->constrained(table: 'design_assets')
                ->nullOnDelete();

            // Fuente tipográfica para capas de texto (opcional)
            $table->foreignId(column: 'design_font_id')
                ->nullable()
                ->constrained(table: 'design_fonts')
                ->nullOnDelete();

            // Gráfico decorativo asociado a la capa (opcional)
            $table->foreignId(column: 'design_graphic_id')
                ->nullable()
                ->constrained(table: 'design_graphics')
                ->nullOnDelete();

            // Tipo de capa: 'text', 'image', 'graphic', 'shape'
            $table->string(column: 'layer_type', length: 40)->index();

            // Nombre de la capa para identificación en el editor
            $table->string(column: 'name')->nullable();

            // Contenido de texto (solo para capas de tipo texto)
            $table->text(column: 'text_content')->nullable();

            // Color del texto en formato hexadecimal
            $table->string(column: 'color', length: 20)->nullable();

            // Color de fondo de la capa
            $table->string(column: 'background_color', length: 20)->nullable();

            // Alineación del texto: 'left', 'center', 'right'
            $table->string(column: 'alignment', length: 30)->nullable();

            // Posición de la capa dentro del área de impresión
            $table->decimal(column: 'position_x', total: 12, places: 4)->default(value: 0);
            $table->decimal(column: 'position_y', total: 12, places: 4)->default(value: 0);

            // Dimensiones de la capa (null para dimensiones automáticas)
            $table->decimal(column: 'width', total: 12, places: 4)->nullable();
            $table->decimal(column: 'height', total: 12, places: 4)->nullable();

            // Escala de la capa (1 = tamaño original)
            $table->decimal(column: 'scale_x', total: 10, places: 4)->default(value: 1);
            $table->decimal(column: 'scale_y', total: 10, places: 4)->default(value: 1);

            // Rotación en grados (0-360)
            $table->decimal(column: 'rotation', total: 8, places: 3)->default(value: 0);

            // Opacidad de la capa (0-1, donde 1 es completamente opaca)
            $table->decimal(column: 'opacity', total: 5, places: 4)->default(value: 1);

            // Orden de apilamiento de capas (mayor = más arriba)
            $table->integer(column: 'z_index')->default(value: 0)->index();

            // Indica si la capa está bloqueada para edición
            $table->boolean(column: 'locked')->default(value: false);

            // Indica si la capa es visible en el canvas
            $table->boolean(column: 'visible')->default(value: true)->index();

            // Estilos CSS adicionales en JSON (sombras, bordes, etc.)
            $table->json(column: 'styles')->nullable();

            // Efectos visuales aplicados en JSON (blur, glow, etc.)
            $table->json(column: 'effects')->nullable();

            $table->timestamps();

            // Índice para ordenar capas por diseño, área y z-index
            $table->index(columns: ['custom_design_id', 'print_area_id', 'z_index'], name: 'design_layers_design_area_z_idx');
            // Índice para filtrar capas por área y tipo
            $table->index(columns: ['print_area_id', 'layer_type'], name: 'design_layers_area_type_idx');
        });
    }

    /**
     * Elimina la tabla de capas de diseño.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'design_layers');
    }
};
