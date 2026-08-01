<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que define las áreas de impresión disponibles para cada personalización de producto.
     * Cada área de impresión especifica dimensiones, posición y zona segura para el diseño.
     */
    public function up(): void
    {
        Schema::create(table: 'print_areas', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cada área de impresión pertenece a una personalización de producto
            $table->foreignId(column: 'product_customization_id')
                ->constrained(table: 'product_customizations')
                ->cascadeOnDelete();

            // Nombre legible del área (ej: "Frontal", "Trasera")
            $table->string(column: 'name');

            // Código técnico único dentro de la personalización (ej: "front", "back")
            $table->string(column: 'code', length: 80);

            // Vista del mockup al que corresponde este área
            $table->string(column: 'view', length: 40)->index();

            // Dimensiones físicas en pulgadas (para impresión real)
            $table->decimal(column: 'width_inches', total: 8, places: 2)->nullable();
            $table->decimal(column: 'height_inches', total: 8, places: 2)->nullable();

            // Dimensiones en píxeles (para diseño digital)
            $table->unsignedInteger(column: 'width_px');
            $table->unsignedInteger(column: 'height_px');

            // Resolución de impresión; 300 DPI es estándar para calidad fotográfica
            $table->unsignedSmallInteger(column: 'dpi')->default(value: 300)->index();

            // Posición absoluta del área dentro del mockup (en píxeles)
            $table->decimal(column: 'position_x', total: 10, places: 4)->default(value: 0);
            $table->decimal(column: 'position_y', total: 10, places: 4)->default(value: 0);

            // Dimensiones relativas del área (0-1) para renderizado responsive
            $table->decimal(column: 'relative_width', total: 10, places: 4)->nullable();
            $table->decimal(column: 'relative_height', total: 10, places: 4)->nullable();

            // Máscara de recorte en formato JSON (path SVG o coordenadas)
            $table->json(column: 'mask')->nullable();

            // Zona segura donde el diseño no se recortará (márgenes de seguridad)
            $table->json(column: 'safe_zone')->nullable();

            // Estado de disponibilidad del área de impresión
            $table->boolean(column: 'active')->default(value: true)->index();

            $table->timestamps();

            // Unicidad: cada código de área es único dentro de una personalización
            $table->unique(columns: ['product_customization_id', 'code'], name: 'print_area_custom_code_unique');
            // Índice para buscar áreas por personalización y vista
            $table->index(columns: ['product_customization_id', 'view'], name: 'print_area_custom_view_idx');
        });
    }

    /**
     * Elimina la tabla de áreas de impresión.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'print_areas');
    }
};
