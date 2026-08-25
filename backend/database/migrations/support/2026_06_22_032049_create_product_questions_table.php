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
     * Crea la tabla de preguntas de productos para gestionar
     * las consultas de clientes sobre productos del catálogo.
     */
    public function up(): void
    {
        Schema::create(table: 'product_questions', callback: function (Blueprint $table): void {
            $table->id();

            // Producto preguntado: cascadeOnDelete porque si se elimina el producto sus preguntas no tienen sentido
            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Cliente que realizó la pregunta: cascadeOnDelete porque la pregunta se mantiene en esta tabla
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Store asociado al producto, nullable para preguntas de productos globales
            // restrictOnDelete: no se debe eliminar un store que tenga preguntas asociadas
            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->restrictOnDelete();

            // Texto de la pregunta del cliente (obligatorio)
            $table->text(column: 'question');
            // Respuesta del vendedor u otro cliente, nullable hasta que se responda
            $table->text(column: 'answer')->nullable();
            // Fecha y hora en que se realizó la pregunta
            $table->timestamp(column: 'asked_at')->useCurrent();
            // Fecha y hora en que se respondió la pregunta
            $table->timestamp(column: 'answered_at')->nullable();

            $table->timestamps();
            // Soft deletes para conservar historial de preguntas eliminadas
            $table->softDeletes();

            $table->index(columns: ['product_id'], name: 'product_questions_product_id_idx');
            $table->index(columns: ['customer_id'], name: 'product_questions_customer_id_idx');
            $table->index(columns: ['store_id'], name: 'product_questions_store_id_idx');
            $table->index(columns: ['asked_at'], name: 'product_questions_asked_at_idx');
            $table->index(columns: ['answered_at'], name: 'product_questions_answered_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'product_questions');
    }
};
