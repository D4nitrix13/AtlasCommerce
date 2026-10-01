<?php

declare(strict_types=1);

namespace App\Models\Account;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item del bulk-cart de un cliente (B2B / grandes pedidos).
 *
 * Tabla: `bulk_cart_items`. Se persiste en DB para sobrevivir entre
 * sesiones; se consume desde el flujo RFQ (Request For Quote).
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property string|null $notes
 */
class BulkCartItem extends Model
{
    use HasFactory;

    protected $table = 'bulk_cart_items';

    protected $fillable = [
        'customer_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Route model binding: usa el PK `id` por defecto, sin inferir el
     * placeholder singularizado `{bulk_cart}` como columna (que no existe).
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
