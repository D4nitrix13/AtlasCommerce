<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena las fuentes tipográficas disponibles para los diseños personalizados.
     * Cada fuente se identifica por familia, estilo y peso para permitir variaciones.
     */
    public function up(): void
    {
        Schema::create(table: 'design_fonts', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Nombre comercial de la fuente (ej: "Montserrat", "Roboto")
            $table->string(column: 'name');

            // Familia tipográfica para agrupar variantes
            $table->string(column: 'family')->index();

            // Estilo de la fuente: 'normal', 'italic', 'oblique'
            $table->string(column: 'style', length: 40)->default(value: 'normal');

            // Peso de la fuente: '400' (regular), '700' (bold), etc.
            $table->string(column: 'weight', length: 20)->default(value: '400');

            // Ruta al archivo de fuente en disco (null para fuentes del sistema)
            $table->string(column: 'file_path')->nullable();

            // Tipo de licencia: 'free', 'commercial', 'open-source'
            $table->string(column: 'license')->nullable();

            // Estado de disponibilidad de la fuente
            $table->boolean(column: 'active')->default(value: true)->index();

            $table->timestamps();

            // Unicidad: cada combinación familia+estilo+peso es única
            $table->unique(columns: ['family', 'style', 'weight'], name: 'design_fonts_family_style_weight_unique');
        });
    }

    /**
     * Elimina la tabla de fuentes de diseño.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'design_fonts');
    }
};
