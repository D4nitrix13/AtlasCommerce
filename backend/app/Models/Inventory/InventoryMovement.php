<?php

namespace App\Models\Inventory;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de entrada o salida de inventario.
 *
 * Tabla: `inventory_movements` — relación N:1 con `inventories` y `users`.
 */
class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_id',
        'user_id',
        'movement_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'movement_date' => 'datetime',
    ];

    /**
     * Registro de inventario afectado.
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Usuario que realizó el movimiento (opcional — movimientos automáticos).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeByInventory($query, int $inventoryId)
    {
        return $query->where('inventory_id', $inventoryId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('movement_date', 'desc');
    }
}
