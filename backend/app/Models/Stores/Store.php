<?php

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tienda virtual del ecosistema.
 *
 * Tabla: `stores` — relación 1:N con `store_domains`, `store_social_links`, `product_stores`, `user_stores`, `customer_stores`, `sales_channels` y `store_settings`.
 *
 * Entidad central que agrupa todos los elementos de una tienda:
 * productos, usuarios, clientes, dominios, configuración y canales de venta.
 */
class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'currency_id',
        'name',
        'slug',
        'logo',
        'favicon',
        'cover_image',
        'og_image',
        'email',
        'phone',
        'whatsapp',
        'address',
        'description',
        'primary_currency',
        'primary_color',
        'secondary_color',
        'maintenance_mode',
        'is_active',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Dominios asociados a la tienda.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    /**
     * Enlaces a redes sociales de la tienda.
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(StoreSocialLink::class);
    }

    /**
     * Productos publicados en la tienda.
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductStore::class);
    }

    /**
     * Usuarios con acceso a la tienda.
     */
    public function users(): HasMany
    {
        return $this->hasMany(UserStore::class);
    }

    /**
     * Clientes registrados en la tienda.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(CustomerStore::class);
    }

    /**
     * Canales de venta configurados.
     */
    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }

    /**
     * Configuraciones de la tienda.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    /**
     * Dominio principal activo de la tienda.
     */
    public function primaryDomain(): ?StoreDomain
    {
        return $this->domains()
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }
}
