<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de medios para almacenar archivos multimedia
     * asociados a cualquier modelo del sistema.
     */
    public function up(): void
    {
        Schema::create(table: 'media', callback: function (Blueprint $table): void {
            $table->id();

            // Relación polimórfica: cada medio pertenece a un modelo (producto, categoría, etc.)
            $table->morphs(name: 'model');
            // UUID opcional para acceso público seguro a los archivos
            $table->uuid()->nullable()->unique();
            // Nombre de la colección agrupadora (ej: 'gallery', 'thumbnail', 'document')
            $table->string(column: 'collection_name');
            // Nombre descriptivo del archivo multimedia
            $table->string(column: 'name');
            // Nombre original del archivo en disco
            $table->string(column: 'file_name');
            // Tipo MIME del archivo, nullable por si no se puede determinar
            $table->string(column: 'mime_type')->nullable();
            // Disco de almacenamiento (local, s3, etc.)
            $table->string(column: 'disk');
            // Disco utilizado para conversiones de imagen, nullable si no aplica
            $table->string(column: 'conversions_disk')->nullable();
            // Tamaño del archivo en bytes
            $table->unsignedBigInteger('size');
            // Manipulaciones aplicadas al archivo en formato JSON serializado
            $table->json(column: 'manipulations');
            // Propiedades personalizadas del archivo en formato JSON serializado
            $table->json(column: 'custom_properties');
            // Conversiones generadas (thumbnails, etc.) en formato JSON serializado
            $table->json(column: 'generated_conversions');
            // Imágenes responsivas generadas en formato JSON serializado
            $table->json(column: 'responsive_images');
            // Columna de ordenamiento para ordenar medios dentro de una colección
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }
};
