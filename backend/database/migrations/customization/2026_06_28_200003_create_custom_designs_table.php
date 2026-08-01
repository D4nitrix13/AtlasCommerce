<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena los diseños personalizados creados por clientes o usuarios.
     * Un diseño se asocia a un producto específico y contiene la configuración del canvas,
     * vista previa y archivo de producción.
     */
    public function up(): void
    {
        Schema::create(table: 'custom_designs', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // El cliente es opcional; permite diseños anónimos que luego se asocian
            $table->foreignId(column: 'customer_id')
                ->nullable()
                ->constrained(table: 'customers')
                ->nullOnDelete();

            // El usuario autenticado que creó el diseño (también opcional para invitados)
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // Producto al que pertenece el diseño; no se puede eliminar el producto si tiene diseños
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->restrictOnDelete();

            // Variante específica del producto (opcional)
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->restrictOnDelete();

            // Personalización del producto que define las áreas de impresión disponibles
            $table->foreignId(column: 'product_customization_id')
                ->constrained(table: 'product_customizations')
                ->restrictOnDelete();

            // Nombre del diseño asignado por el usuario
            $table->string(column: 'name')->nullable();

            // Estado del diseño: 'draft', 'completed', 'approved', 'rejected'
            $table->string(column: 'status', length: 40)->default(value: 'draft')->index();

            // Color de fondo del canvas en formato hexadecimal
            $table->string(column: 'background_color', length: 20)->default(value: '#ffffff');

            // Dimensiones del canvas de diseño en píxeles
            $table->unsignedInteger('canvas_width_px');
            $table->unsignedInteger('canvas_height_px');

            // Configuraciones adicionales del canvas en JSON
            $table->json(column: 'canvas_settings')->nullable();

            // URL de la imagen de vista previa generada
            $table->string(column: 'preview_url')->nullable();

            // URL del archivo de producción (SVG, PDF) para impresión
            $table->string(column: 'production_file_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices compuestos para búsquedas frecuentes por cliente/usuario y estado
            $table->index(columns: ['customer_id', 'status'], name: 'custom_design_customer_status_idx');
            $table->index(columns: ['user_id', 'status'], name: 'custom_design_user_status_idx');
            // Índice para buscar diseños por producto y variante
            $table->index(columns: ['product_id', 'product_variant_id'], name: 'custom_design_product_variant_idx');
        });
    }

    /**
     * Elimina la tabla de diseños personalizados.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'custom_designs');
    }
};
