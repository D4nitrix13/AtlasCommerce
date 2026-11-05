<?php

declare(strict_types=1);

namespace App\Models\Storefront;

use App\Models\Catalog\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de "producto visto recientemente" en la PDP.
 *
 * Tabla: `recently_viewed_products`. Una fila por identidad + producto:
 * la identidad es `user_id` (cliente autenticado) o `guest_token`
 * (visitante con token opaco del frontend), nunca ambos. La ventana de
 * retención es permanente (sliding window sin reset periódico) con cap
 * de `RecentlyViewedService::MAX_ENTRIES` por identidad.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property int $product_id
 * @property Carbon $viewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 */
class RecentlyViewedProduct extends Model
{
    protected $table = 'recently_viewed_products';

    protected $fillable = [
        'user_id',
        'guest_token',
        'product_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
