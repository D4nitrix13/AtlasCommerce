<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nivel de inventario de un producto o variante.
 *
 * Tabla: `inventories` — relación N:1 con `products`, `product_variants`
 * y `warehouses`, y 1:N con `inventory_movements`.
 *
 * Cada registro representa el stock de un producto/variante en un almacén específico.
 * La restricción unique en (warehouse_id, product_id, product_variant_id) garantiza
 * un solo registro por combinación.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $current_stock
 * @property int $minimum_stock
 * @property int $reserved_stock
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
     * Stock disponible para venta (actual - reservado).
     *
     * @return int Cantidad disponible.
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->current_stock - $this->reserved_stock;
    }

    /**
     * Determina si el stock está por debajo del mínimo configurado.
     *
     * @return bool True cuando el stock actual es menor o igual al mínimo.
     */
    public function isLow(): bool
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    /**
     * Filtra registros con stock bajo (actual <= mínimo).
     *
     * @param  Builder<Inventory>  $query  Consulta del modelo.
     * @return Builder<Inventory>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where(column: 'current_stock', operator: '<=', column2: 'minimum_stock');
    }

    /**
     * Filtra registros por almacén específico.
     *
     * @param  Builder<Inventory>  $query  Consulta del modelo.
     * @param  int  $warehouseId  Identificador del almacén.
     * @return Builder<Inventory>
     */
    public function scopeByWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where(column: 'warehouse_id', value: $warehouseId);
    }

    /**
     * Filtra registros por producto específico.
     *
     * @param  Builder<Inventory>  $query  Consulta del modelo.
     * @param  int  $productId  Identificador del producto.
     * @return Builder<Inventory>
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where(column: 'product_id', value: $productId);
    }
}
