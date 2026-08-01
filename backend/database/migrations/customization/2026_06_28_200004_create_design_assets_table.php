<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla que almacena los archivos multimedia subidos por usuarios/clientes
     * para usar en sus diseños personalizados (imágenes, SVG, etc.).
     */
    public function up(): void
    {
        Schema::create(table: 'design_assets', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Usuario propietario del asset (opcional para clientes no autenticados)
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete();

            // Cliente propietario del asset (opcional para usuarios autenticados)
            $table->foreignId(column: 'customer_id')
                ->nullable()
                ->constrained(table: 'customers')
                ->nullOnDelete();

            // Nombre original del archivo subido por el usuario
            $table->string(column: 'original_name');

            // Nombre del archivo almacenado en disco (UUID o hash)
            $table->string(column: 'file_name');

            // Tipo MIME del archivo para validación en el frontend
            $table->string(column: 'mime_type', length: 120)->index();

            // Extensión del archivo (jpg, png, svg, etc.)
            $table->string(column: 'extension', length: 20)->index();

            // Disco de almacenamiento: 'public', 's3', 'local', etc.
            $table->string(column: 'storage_disk')->default(value: 'public');

            // Ruta completa del archivo en el disco seleccionado
            $table->string(column: 'file_path');

            // Tamaño del archivo en bytes para validación de límites
            $table->unsignedBigInteger(column: 'size_bytes');

            // Dimensiones de la imagen (null para archivos no imagen)
            $table->unsignedInteger(column: 'width_px')->nullable();
            $table->unsignedInteger(column: 'height_px')->nullable();
            $table->unsignedSmallInteger(column: 'dpi')->nullable();

            // Checksum MD5/SHA para detectar duplicados y verificar integridad
            $table->string(column: 'checksum', length: 128)->nullable()->index();

            // Origen del asset: 'upload', 'library', 'stock', etc.
            $table->string(column: 'source', length: 40)->default(value: 'upload')->index();

            // Estado del asset: 'active', 'deleted', 'pending_review'
            $table->string(column: 'status', length: 40)->default(value: 'active')->index();

            $table->timestamps();
            $table->softDeletes();

            // Índices para buscar assets por cliente/usuario y estado
            $table->index(columns: ['customer_id', 'status'], name: 'design_assets_customer_status_idx');
            $table->index(columns: ['user_id', 'status'], name: 'design_assets_user_status_idx');
        });
    }

    /**
     * Elimina la tabla de assets de diseño.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'design_assets');
    }
};
