<?php

namespace App\Models\Stores;

use App\Models\Settings\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tienda virtual del ecosistema.
 *
 * Tabla: `stores` — relación 1:N con `store_domains`, `store_social_links`,
 * `product_stores`, `user_stores`, `customer_stores`, `sales_channels`
 * y `store_settings`. N:1 con `currencies`.
 *
 * Entidad central que agrupa todos los elementos de una tienda:
 * productos, usuarios, clientes, dominios, configuración y canales de venta.
 *
 * @property int $id
 * @property int|null $currency_id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $cover_image_path
 * @property string|null $og_image_path
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $address
 * @property string|null $description
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property bool $maintenance_mode
 * @property bool $is_active
 */
class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'currency_id',
        'name',
        'slug',
        'logo_path',
        'favicon_path',
        'cover_image_path',
        'og_image_path',
        'email',
        'phone',
        'whatsapp',
        'address',
        'description',
        'primary_color',
        'secondary_color',
        'maintenance_mode',
        'is_active',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(StoreSocialLink::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductStore::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(UserStore::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(CustomerStore::class);
    }

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    public function primaryDomain(): ?StoreDomain
    {
        return $this->domains()
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }
}
