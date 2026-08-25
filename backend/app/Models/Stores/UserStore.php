<?php

declare(strict_types=1);

namespace App\Models\Stores;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación entre un usuario y una tienda.
 *
 * Tabla: `user_stores` — relación N:1 con `users` y `stores`.
 *
 * Vincula un usuario con la tienda en la que tiene permisos de gestión.
 */
class UserStore extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
    ];

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Usuario asociado.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
