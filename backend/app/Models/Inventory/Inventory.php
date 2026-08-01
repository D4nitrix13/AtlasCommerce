<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nivel de inventario de un producto o variante.
 *
 * Tabla: `inventories` — relación N:1 con `products`, `product_variants`
 * y `warehouses`, y 1:N con `inventory_movements`.
 */
class Inventory extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'current_stock',
        'minimum_stock',
        'reserved_stock',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'minimum_stock' => 'integer',
        'reserved_stock' => 'integer',
    ];

    /**
     * Almacén del inventario.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Producto del inventario.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante del producto (opcional).
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Movimientos de inventario.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Stock disponible (actual - reservado).
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->current_stock - $this->reserved_stock;
    }

    /**
     * Determina si el stock está por debajo del mínimo.
     */
    public function isLow(): bool
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock');
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }
}
