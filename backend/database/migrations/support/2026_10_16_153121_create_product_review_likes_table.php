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
     * Crea la tabla pivot `product_review_likes` para registrar
     * los "me gusta" que los clientes dan a reseñas de productos.
     * Cada combinación (reseña, cliente) es única — un cliente
     * solo puede likear una reseña una vez (toggle on/off vía delete).
     */
    public function up(): void
    {
        Schema::create(table: 'product_review_likes', callback: function (Blueprint $table): void {
            $table->id();

            // Reseña liked: cascadeOnDelete porque al borrar la reseña sus likes no tienen sentido
            $table->foreignId(column: 'product_review_id')
                ->constrained(table: 'product_reviews')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Cliente que likéa: cascadeOnDelete porque al borrar el cliente se eliminan sus likes
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // updated_at se mantiene por consistencia con el resto del repo,
            // aunque semánticamente los likes son inmutables (solo create/delete).
            $table->timestamps();

            // Un cliente solo puede likear una reseña una vez — la duplicidad
            // se traduce en idempotencia (segundo insert falla por unique).
            $table->unique(columns: ['product_review_id', 'customer_id'], name: 'product_review_likes_review_customer_unique');

            // Índices para conteo de likes por reseña y listado de likes por cliente
            $table->index(columns: ['product_review_id'], name: 'product_review_likes_product_review_id_idx');
            $table->index(columns: ['customer_id'], name: 'product_review_likes_customer_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_review_likes');
    }
};
