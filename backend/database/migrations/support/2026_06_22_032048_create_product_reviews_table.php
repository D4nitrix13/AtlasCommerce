<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de reseñas de productos para recopilar
     * opiniones y calificaciones de clientes sobre productos.
     */
    public function up(): void
    {
        Schema::create(table: 'product_reviews', callback: function (Blueprint $table): void {
            $table->id();

            // Producto reseñado: cascadeOnDelete porque si se elimina el producto sus reseñas no tienen sentido
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Cliente que escribió la reseña: cascadeOnDelete porque la reseña se mantiene en esta tabla
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Store asociado al producto reseñado, nullable para reseñas de productos globales
            // restrictOnDelete: no se debe eliminar un store que tenga reseñas asociadas
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Calificación numérica del producto (ej: 1-5 estrellas)
            $table->unsignedTinyInteger(column: 'rating');
            // Comentario escrito por el cliente, nullable si solo califica sin escribir
            $table->text(column: 'comment')->nullable();
            // Estado de aprobación: controla la visibilidad pública de la reseña
            $table->boolean(column: 'is_approved')->default(value: false);
            // Fecha y hora en que se publicó la reseña
            $table->timestamp(column: 'reviewed_at')->useCurrent();

            $table->timestamps();
            // Soft deletes para conservar historial de reseñas eliminadas
            $table->softDeletes();

            $table->index(columns: ['product_id'], name: 'product_reviews_product_id_idx');
            $table->index(columns: ['customer_id'], name: 'product_reviews_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'product_reviews_store_id_idx');
            $table->index(columns: ['rating'], name: 'product_reviews_rating_idx');
            $table->index(columns: ['is_approved'], name: 'product_reviews_is_approved_idx');
            $table->index(columns: ['reviewed_at'], name: 'product_reviews_reviewed_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_reviews');
    }
};
