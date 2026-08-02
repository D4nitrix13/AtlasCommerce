<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Theme visual de una tienda.
 *
 * Tabla: `store_themes` — relación 1:1 con `stores`.
 *
 * Define el branding que consume el frontend: logo, colores, fuentes,
 * datos de contacto. La columna `tokens` (JSON) permite extender
 * con tokens custom (fonts, spacing, etc.) sin migrar.
 *
 * Se consulta vía `GET /api/storefront/theme` con el store resuelto
 * por `ResolveStoreFromDomain` middleware.
 *
 * @property int $id
 * @property int $store_id
 * @property string|null $short_name
 * @property string|null $logo_path
 * @property string|null $login_logo_path
 * @property string|null $favicon_path
 * @property string|null $accent_color
 * @property string|null $accent_hover_color
 * @property string|null $primary_color
 * @property string|null $support_email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property array<string, mixed>|null $tokens
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class StoreTheme extends Model
{
    use HasFactory;

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'short_name',
        'logo_path',
        'login_logo_path',
        'favicon_path',
        'accent_color',
        'accent_hover_color',
        'primary_color',
        'support_email',
        'phone',
        'whatsapp',
        'tokens',
    ];

    /**
     * Casts automaticos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'array',
        ];
    }

    /**
     * Tienda a la que pertenece este theme.
     *
     * @return BelongsTo<Store, StoreTheme>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(related: Store::class, foreignKey: 'store_id');
    }
}
