<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla `recovery_codes` que almacena códigos de recuperación
     * hasheados. Cada código es de un solo uso y pertenece a un lote específico.
     */
    public function up(): void
    {
        Schema::create(table: 'recovery_codes', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Cascade on delete: si se elimina el usuario, se eliminan sus códigos de recuperación
            $table->foreignId(column: 'user_id')
                ->constrained(table: 'users')
                ->cascadeOnDelete();

            // UUID del lote para agrupar códigos generados juntos (regeneración masiva)
            // index() para buscar todos los códigos de un lote específico
            $table->uuid(column: 'batch_uuid')->index();
            // Hash del código, nunca se almacena el código en texto plano
            $table->string(column: 'code_hash');

            // index() en used_at para consultar códigos usados y facilitar limpieza
            $table->timestamp(column: 'used_at')->nullable()->index();

            $table->timestamps();

            // unique() compuesto para evitar duplicados del mismo código por usuario
            $table->unique(columns: ['user_id', 'code_hash'], name: 'recovery_user_code_unique');
            // Índice compuesto para consultar códigos no usados de un usuario específico
            $table->index(columns: ['user_id', 'used_at'], name: 'recovery_user_used_idx');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'recovery_codes');
    }
};
