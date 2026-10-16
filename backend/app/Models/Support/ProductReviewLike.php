<?php

declare(strict_types=1);

namespace App\Models\Support;

use App\Models\Customers\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Me gusta" que un cliente da a una reseña de producto.
 *
 * Tabla pivot: `product_review_likes` — relación N:1 con
 * `product_reviews` y N:1 con `customers`. La combinación
 * (product_review_id, customer_id) es única — el "dislike"
 * se implementa eliminando la fila.
 *
 * Los likes son inmutables: no se actualizan, solo se crean
 * o eliminan. Aun así `updated_at` se conserva por consistencia
 * con el resto del modelo de datos del repo.
 *
 * @property int $id
 * @property int $product_review_id
 * @property int $customer_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductReviewLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_review_id',
        'customer_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Reseña que recibió el "me gusta".
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(related: ProductReview::class, foreignKey: 'product_review_id');
    }

    /**
     * Cliente que dio el "me gusta".
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: Customer::class);
    }
}
