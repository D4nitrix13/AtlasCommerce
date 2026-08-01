<?php

namespace App\Models\Customization;

use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customers\Customer;
use App\Models\Sales\OrderCustomDesign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Diseño personalizado subido por un cliente para un producto.
 *
 * Tabla: `custom_designs` — relación N:1 con `customers`, `users`, `products`
 * y `product_variants`.
 *
 * Almacena el archivo de producción subido por el usuario y un preview opcional.
 * Para MVP no incluye editor visual (`design_layers`); cuando se agregue el
 * editor se suma de vuelta en una migración nueva sin tocar esta estructura.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $name
 * @property string $status
 * @property string|null $preview_url
 * @property string|null $production_file_url
 * @property array|null $metadata
 */
class CustomDesign extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'product_id',
        'product_variant_id',
        'name',
        'status',
        'preview_url',
        'production_file_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function orderCustomDesigns(): HasMany
    {
        return $this->hasMany(OrderCustomDesign::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
