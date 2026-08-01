<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea la tabla de ítems de solicitud de devolución para
     * desglosar los productos incluidos en cada devolución.
     */
    public function up(): void
    {
        Schema::create(table: 'return_request_items', callback: function (Blueprint $table): void {
            $table->id();

            // Solicitud de devolución a la que pertenece el ítem: cascadeOnDelete porque si se elimina la solicitud sus ítems no tienen sentido
            $table->foreignId(column: 'return_request_id')
                ->constrained(table: 'return_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Producto devuelto: restrictOnDelete porque no se debe eliminar un producto que tenga devoluciones registradas
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Variante del producto devuelto, nullable para devoluciones a nivel de producto base
            // nullOnDelete: si se elimina la variante, el ítem de devolución se mantiene vinculado al producto base
            $table->foreignId(column: 'product_variant_id')
                ->nullable()
                ->constrained(table: 'product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Cantidad de unidades a devolver de este ítem
            $table->unsignedInteger('quantity');
            // Monto a devolver por este ítem (quantity * precio unitario original)
            $table->decimal(column: 'amount', total: 12, places: 2);

            $table->timestamps();

            $table->index(columns: ['return_request_id'], name: 'return_request_items_return_request_id_idx');
            $table->index(columns: ['product_id'], name: 'return_request_items_product_id_idx');
            $table->index(columns: ['product_variant_id'], name: 'return_request_items_product_variant_id_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'return_request_items');
    }
};
