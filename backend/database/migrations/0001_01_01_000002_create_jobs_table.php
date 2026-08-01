<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * Crea las tablas necesarias para el sistema de colas de Laravel,
     * incluyendo trabajos individuales, lotes y registros de fallos.
     */
    public function up(): void
    {
        Schema::create(table: 'jobs', callback: function (Blueprint $table): void {
            $table->id();
            // La cola permite agrupar trabajos por tipo o prioridad
            $table->string(column: 'queue')->index();
            $table->longText(column: 'payload');
            // Número máximo de intentos antes de marcar como fallido
            $table->unsignedSmallInteger(column: 'attempts');
            // TimestampUnix de cuando se reservó el trabajo para procesamiento
            $table->unsignedInteger(column: 'reserved_at')->nullable();
            // TimestampUnix de cuando el trabajo estará disponible para procesar
            $table->unsignedInteger(column: 'available_at');
            $table->unsignedInteger(column: 'created_at');
        });

        Schema::create(table: 'job_batches', callback: function (Blueprint $table): void {
            $table->string(column: 'id')->primary();
            $table->string(column: 'name');
            $table->integer(column: 'total_jobs');
            $table->integer(column: 'pending_jobs');
            $table->integer(column: 'failed_jobs');
            // IDs de trabajos fallidos en formato JSON serializado
            $table->longText(column: 'failed_job_ids');
            // Opciones del lote en formato JSON serializado, nullable por si no se configuran
            $table->mediumText(column: 'options')->nullable();
            $table->integer(column: 'cancelled_at')->nullable();
            $table->integer(column: 'created_at');
            $table->integer(column: 'finished_at')->nullable();
        });

        Schema::create(table: 'failed_jobs', callback: function (Blueprint $table): void {
            $table->id();
            // UUID único para identificar cada trabajo de forma inequívoca
            $table->string(column: 'uuid')->unique();
            $table->string(column: 'connection');
            $table->string(column: 'queue');
            $table->longText(column: 'payload');
            // Excepción completa serializada para debugging
            $table->longText(column: 'exception');
            $table->timestamp(column: 'failed_at')->useCurrent();

            // Índice compuesto para búsquedas por conexión, cola y fecha de fallo
            $table->index(columns: ['connection', 'queue', 'failed_at'], name: 'jobs_connection_queue_failed_at_idx');
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'failed_jobs');
        Schema::dropIfExists(table: 'job_batches');
        Schema::dropIfExists(table: 'jobs');
    }
};
