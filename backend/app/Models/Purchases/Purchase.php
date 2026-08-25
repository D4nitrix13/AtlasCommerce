<?php

declare(strict_types=1);

namespace App\Models\Purchases;

use App\Models\Auth\User;
use App\Models\Settings\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Orden de compra a un proveedor.
 *
 * Tabla: `purchases` — relación N:1 con `suppliers`, `users`, `currencies`.
 * 1:N con `purchase_items`.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int|null $user_id
 * @property int $currency_id
 * @property string $purchase_number
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $discount_cents
 * @property int $total_cents
 * @property string $status
 * @property Carbon $purchased_at
 * @property string|null $notes
 */
class Purchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'currency_id',
        'purchase_number',
        'subtotal_cents',
        'tax_cents',
        'discount_cents',
        'total_cents',
        'status',
        'purchased_at',
        'notes',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'tax_cents' => 'integer',
        'discount_cents' => 'integer',
        'total_cents' => 'integer',
        'purchased_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }
}
