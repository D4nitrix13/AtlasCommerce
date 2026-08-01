<?php

namespace App\Models\Inventory;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de entrada o salida de inventario.
 *
 * Tabla: `inventory_movements` — relación N:1 con `inventories` y `users`.
 *
 * Registra cada transacción de stock con tipo (purchase, sale, adjustment, return),
 * cantidades anteriores y nuevas para auditoría completa.
 *
 * @property int $id
 * @property int $inventory_id
 * @property int|null $user_id
 * @property string $movement_type
 * @property int $quantity
 * @property int $previous_stock
 * @property int $new_stock
 * @property string|null $reason
 * @property Carbon $movement_date
 */
class InventoryMovement extends Model
{
    use HasFactory;

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

    /**
     * Filtra movimientos por tipo específico (purchase, sale, adjustment, return).
     *
     * @param  Builder<InventoryMovement>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de movimiento.
     * @return Builder<InventoryMovement>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'movement_type', value: $type);
    }

    /**
     * Filtra movimientos por registro de inventario específico.
     *
     * @param  Builder<InventoryMovement>  $query  Consulta del modelo.
     * @param  int  $inventoryId  Identificador del inventario.
     * @return Builder<InventoryMovement>
     */
    public function scopeByInventory(Builder $query, int $inventoryId): Builder
    {
        return $query->where(column: 'inventory_id', value: $inventoryId);
    }

    /**
     * Ordena movimientos del más reciente al más antiguo.
     *
     * @param  Builder<InventoryMovement>  $query  Consulta del modelo.
     * @return Builder<InventoryMovement>
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy(column: 'movement_date', direction: 'desc');
    }
}
