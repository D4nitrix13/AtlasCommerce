<?php

declare(strict_types=1);

namespace App\Models\Support;

use App\Models\Catalog\Product;
use App\Models\Concerns\BelongsToStore;
use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pregunta de un cliente sobre un producto.
 *
 * Tabla: `product_questions` — relación N:1 con `products`, `customers` y `stores`.
 *
 * Las preguntas quedan pendientes hasta que un administrador
 * proporciona una respuesta.
 *
 * @property int $id
 * @property int $product_id
 * @property int $customer_id
 * @property int|null $store_id
 * @property string $question
 * @property string|null $answer
 * @property Carbon $asked_at
 * @property Carbon|null $answered_at
 */
class ProductQuestion extends Model
{
    use BelongsToStore;
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

    /**
     * Filtra preguntas respondidas.
     *
     * @param  Builder<ProductQuestion>  $query  Consulta del modelo.
     * @return Builder<ProductQuestion>
     */
    public function scopeAnswered(Builder $query): Builder
    {
        return $query->whereNotNull(columns: 'answer');
    }

    /**
     * Filtra preguntas pendientes de respuesta.
     *
     * @param  Builder<ProductQuestion>  $query  Consulta del modelo.
     * @return Builder<ProductQuestion>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull(columns: 'answer');
    }

    /**
     * Filtra preguntas por producto específico.
     *
     * @param  Builder<ProductQuestion>  $query  Consulta del modelo.
     * @param  int  $productId  Identificador del producto.
     * @return Builder<ProductQuestion>
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where(column: 'product_id', value: $productId);
    }

    /**
     * Filtra preguntas por cliente específico.
     *
     * @param  Builder<ProductQuestion>  $query  Consulta del modelo.
     * @param  int  $customerId  Identificador del cliente.
     * @return Builder<ProductQuestion>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', value: $customerId);
    }
}
