<?php

namespace App\Models\Catalog;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Categoría del catálogo de productos.
 *
 * Tabla: `categories` — relación jerárquica N:1 consigo misma, 1:N con
 * `products` (vía `category_id`) y N:N con `products` (vía
 * `category_product`). Esto permite que un producto pertenezca a varias
 * categorías simultáneas (`category_id` queda como "categoría principal").
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_path
 * @property bool $is_active
 */
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'category_product',
        );
    }

    /**
     * Filtra categorías activas.
     *
     * @param  Builder<Category>  $query  Consulta del modelo.
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra categorías raíz (sin categoría padre).
     *
     * @param  Builder<Category>  $query  Consulta del modelo.
     * @return Builder<Category>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull(columns: 'parent_id');
    }
}
