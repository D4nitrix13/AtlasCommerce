<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Models\Auth\User;
use App\Models\Concerns\BelongsToStore;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría del sistema.
 *
 * Tabla: `audits` — relación N:1 con `users` y `stores`.
 *
 * Almacena un registro inmutable de cada acción realizada
 * en el sistema con valores anteriores y nuevos para comparación.
 * Los registros de auditoría no deben modificarse ni eliminarse.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $store_id
 * @property string $action
 * @property string $table_name
 * @property string|null $record_id
 * @property string|null $description
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $registered_at
 */
class Audit extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'user_id',
        'store_id',
        'action',
        'table_name',
        'record_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'registered_at' => 'datetime',
    ];

    /**
     * Usuario que realizó la acción.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Filtra auditorías por tipo de acción (created, updated, deleted, etc.).
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @param  string  $action  Tipo de acción realizada.
     * @return Builder<Audit>
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where(column: 'action', operator: '=', value: $action);
    }

    /**
     * Filtra auditorías por tabla específica.
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @param  string  $tableName  Nombre de la tabla afectada.
     * @return Builder<Audit>
     */
    public function scopeByTable(Builder $query, string $tableName): Builder
    {
        return $query->where(column: 'table_name', operator: '=', value: $tableName);
    }

    /**
     * Filtra auditorías por usuario específico.
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @param  int  $userId  Identificador del usuario.
     * @return Builder<Audit>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where(column: 'user_id', operator: '=', value: $userId);
    }

    /**
     * Filtra auditorías por tienda específica.
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @param  int  $storeId  Identificador de la tienda.
     * @return Builder<Audit>
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where(column: 'store_id', operator: '=', value: $storeId);
    }

    /**
     * Filtra auditorías por registro específico (tabla + ID).
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @param  string  $tableName  Nombre de la tabla.
     * @param  int  $recordId  Identificador del registro.
     * @return Builder<Audit>
     */
    public function scopeByRecord(Builder $query, string $tableName, int $recordId): Builder
    {
        return $query->where(column: 'table_name', operator: '=', value: $tableName)
            ->where(column: 'record_id', operator: '=', value: $recordId);
    }

    /**
     * Ordena auditorías de la más reciente a la más antigua.
     *
     * @param  Builder<Audit>  $query  Consulta del modelo.
     * @return Builder<Audit>
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy(column: 'registered_at', direction: 'desc');
    }
}
