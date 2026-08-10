<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabla `erp_connections` por ser código legacy sin uso.
 *
 * La tabla existe en la base de datos desde antes de la auditoría del
 * ER diagram pero no tiene archivo de migración asociado. Tampoco
 * tiene filas (`SELECT COUNT(*) = 0`) ni modelos Eloquent que la
 * consumen. La política RLS `erp_connections_tenant_isolation_policy`
 * también queda obsoleta con la baja de la tabla.
 *
 * Si en el futuro se decide reactivar la integración ERP, el schema
 * de referencia está en esta migration (en `down()`) para tener una
 * base de dónde empezar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists(table: 'erp_connections');
    }

    public function down(): void
    {
        Schema::create(table: 'erp_connections', callback: function (Blueprint $table): void {
            $table->id();
            $table->foreignId(column: 'store_id')
                ->constrained(table: 'stores', column: 'id')
                ->cascadeOnDelete();
            $table->string(column: 'name');
            $table->string(column: 'provider');
            $table->json(column: 'settings')->nullable();
            $table->boolean(column: 'is_active')->default(value: true);
            $table->timestamps();

            $table->unique(columns: ['store_id', 'name'], name: 'erp_connections_store_id_name_unique');
        });
    }
};
