<?php

namespace App\Models\Sales;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customization\CustomDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual dentro de un pedido.
 *
 * Tabla: `order_items` — relación N:1 con `orders`.
 *
 * Referencia al producto, variante, cantidad,
 * precio unitario y subtotal de la línea.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'custom_design_id',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Orden a la que pertenece el ítem.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Producto referenciado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante específica del producto (opcional).
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Diseño personalizado asociado (opcional).
     */
    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }
}
