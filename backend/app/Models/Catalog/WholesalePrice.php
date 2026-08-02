<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio mayorista (wholesale) para un producto en una tienda.
 *
 * Tabla: `wholesale_prices` — relación N:1 con `products` y `stores`.
 *
 * Define un precio especial cuando se compra una cantidad mínima.
 * El servicio `WholesalePricingService` lo consulta cuando el store
 * tiene la feature `wholesale_pricing` activa.
 *
 * Combinación única `(product_id, store_id, min_qty)`.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property int $min_qty
 * @property int $price_cents
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WholesalePrice extends Model
{
    use HasFactory;

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'store_id',
        'min_qty',
        'price_cents',
    ];

    /**
     * Casts automaticos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    /**
     * Producto al que aplica el precio mayorista.
     *
     * @return BelongsTo<Product, WholesalePrice>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(related: Product::class, foreignKey: 'product_id');
    }

    /**
     * Tienda a la que aplica el precio mayorista.
     *
     * @return BelongsTo<Store, WholesalePrice>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(related: Store::class, foreignKey: 'store_id');
    }
}
