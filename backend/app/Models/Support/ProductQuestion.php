<?php

namespace App\Models\Support;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pregunta de un cliente sobre un producto.
 *
 * Tabla: `product_questions` — relación N:1 con `products`, `customers` y `stores`.
 */
class ProductQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'customer_id',
        'store_id',
        'question',
        'answer',
        'answered_at',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
        'answered_at' => 'datetime',
    ];

    /**
     * Producto preguntado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cliente que preguntó.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeAnswered($query)
    {
        return $query->whereNotNull('answer');
    }

    public function scopePending($query)
    {
        return $query->whereNull('answer');
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
