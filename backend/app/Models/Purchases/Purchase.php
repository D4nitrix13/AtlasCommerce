<?php

namespace App\Models\Purchases;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Orden de compra a un proveedor.
 *
 * Tabla: `purchases` — relación N:1 con `suppliers` y 1:N con `purchase_items`.
 */
class Purchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'purchase_number',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'purchase_date' => 'datetime',
    ];

    /**
     * Proveedor de la orden.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Usuario que creó la orden.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ítems de la orden de compra.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
