<?php

namespace App\Models\System;

use App\Models\Auth\User;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría del sistema.
 *
 * Tabla: `audits` — relación N:1 con `users` y `stores`.
 *
 * Almacena un registro inmutable de cada acción realizada
 * en el sistema con valores anteriores y nuevos para comparación.
 */
class Audit extends Model
{
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

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByRecord($query, string $tableName, int $recordId)
    {
        return $query->where('table_name', $tableName)
            ->where('record_id', $recordId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('registered_at', 'desc');
    }
}
