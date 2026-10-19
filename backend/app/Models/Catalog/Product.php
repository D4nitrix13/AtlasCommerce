<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Models\Concerns\HasSlug;
use App\Models\Inventory\Inventory;
use App\Models\Marketing\Promotion;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Producto principal del catálogo.
 *
 * Tabla: `products` — relación 1:N con `product_variants`,
 * `product_images`, `product_attribute_values` y N:N con `stores`
 * a través de `product_stores`. N:1 con `currencies`.
 *
 * @property int $id
 * @property int $category_id
 * @property int|null $brand_id
 * @property int|null $currency_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $sku
 * @property int $base_price_cents
 * @property int $cost_cents
 * @property bool $is_active
 * @property bool $is_exclusive
 * @property Carbon|null $published_at
 */
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'currency_id',
        'name',
        'slug',
        'description',
        'sku',
        'base_price_cents',
        'cost_cents',
        'is_active',
        'is_exclusive',
        'has_condition',
        'has_sizes',
        'published_at',
    ];

    protected $casts = [
        'base_price_cents' => 'integer',
        'cost_cents' => 'integer',
        'is_active' => 'boolean',
        'is_exclusive' => 'boolean',
        'has_condition' => 'boolean',
        'has_sizes' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    /**
     * Características destacadas del producto (icono + label).
     *
     * Ordenadas por `position` ascendente para que el grid de la
     * PDP respete el orden declarado por el seeder.
     */
    public function highlights(): HasMany
    {
        return $this->hasMany(ProductHighlight::class)->orderBy(value: 'position');
    }

    /**
     * Grupos de specs (Cuerpo, Mástil, Electrónica, …) con sus
     * valores anidados. El `with('groupedSpecs.values')` eager-load
     * del consumer resuelve los 2 niveles en 2 queries indexadas.
     */
    public function groupedSpecs(): HasMany
    {
        return $this->hasMany(ProductGroupedSpec::class)->orderBy(value: 'position');
    }

    /**
     * Preguntas frecuentes del producto, ordenadas por posición.
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy(value: 'position');
    }

    /**
     * Bloques de descripción extendida, ordenados por posición.
     */
    public function extendedDescriptions(): HasMany
    {
        return $this->hasMany(ProductExtendedDescription::class)->orderBy(value: 'position');
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            related: ProductStore::class,
            table: 'product_stores',
        )->withPivot(columns: ['is_visible']);
    }

    /**
     * Promociones automáticas asociadas al producto (RF-MKT-061).
     *
     * Relación N:N via `product_promotion`. Usada por
     * `PromotionService::calculateForCart()` para resolver el descuento
     * aplicable a cada item del carrito.
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Promotion::class,
            table: 'product_promotion',
        );
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeExclusive(Builder $query): Builder
    {
        return $query->where('is_exclusive', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', $sku);
    }
}
