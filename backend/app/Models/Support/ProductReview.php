<?php

declare(strict_types=1);

namespace App\Models\Support;

use App\Models\Catalog\Product;
use App\Models\Concerns\BelongsToStore;
use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reseña y calificación de un producto por un cliente.
 *
 * Tabla: `product_reviews` — relación N:1 con `products`, `customers` y `stores`.
 *
 * Las reseñas requieren aprobación antes de ser visibles públicamente.
 * Cada cliente puede leaving solo una reseña por producto.
 *
 * @property int $id
 * @property int $product_id
 * @property int $customer_id
 * @property int|null $store_id
 * @property int $rating
 * @property string|null $comment
 * @property bool $is_approved
 * @property Carbon $reviewed_at
 */
class ProductReview extends Model
{
    use BelongsToStore;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'customer_id',
        'store_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Producto reseñado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cliente que escribió la reseña.
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

    /**
     * Filtra reseñas aprobadas (visibles públicamente).
     *
     * @param  Builder<ProductReview>  $query  Consulta del modelo.
     * @return Builder<ProductReview>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where(column: 'is_approved', value: true);
    }

    /**
     * Filtra reseñas pendientes de aprobación.
     *
     * @param  Builder<ProductReview>  $query  Consulta del modelo.
     * @return Builder<ProductReview>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'is_approved', value: false);
    }

    /**
     * Filtra reseñas por producto específico.
     *
     * @param  Builder<ProductReview>  $query  Consulta del modelo.
     * @param  int  $productId  Identificador del producto.
     * @return Builder<ProductReview>
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where(column: 'product_id', value: $productId);
    }

    /**
     * Filtra reseñas por cliente específico.
     *
     * @param  Builder<ProductReview>  $query  Consulta del modelo.
     * @param  int  $customerId  Identificador del cliente.
     * @return Builder<ProductReview>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', value: $customerId);
    }

    /**
     * Filtra reseñas por calificación específica.
     *
     * @param  Builder<ProductReview>  $query  Consulta del modelo.
     * @param  int  $rating  Calificación (1-5).
     * @return Builder<ProductReview>
     */
    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where(column: 'rating', value: $rating);
    }
}
