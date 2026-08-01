<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Genera automáticamente `slug` a partir del campo `name` cuando
 * el modelo se guarda sin `slug` o con `slug` vacío.
 *
 * Si ya se proporcionó un `slug` explícito al crear/actualizar, se
 * respeta. El slug se sanea con `Str::slug()` y se trunca a 180
 * caracteres para alinearse con el límite de las columnas `slug`.
 *
 * Uso:
 * ```php
 * class Product extends Model
 * {
 *     use HasSlug;
 * }
 * ```
 */
trait HasSlug
{
    /**
     * Hook de boot del trait.
     *
     * Registra los eventos `saving` para autogenerar el slug cuando
     * `name` cambió o `slug` está vacío.
     */
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            if (! empty($model->slug)) {
                return;
            }

            $name = $model->{$model->getSlugSource()} ?? null;

            if (! is_string($name) || trim($name) === '') {
                return;
            }

            $model->slug = Str::limit(Str::slug($name), 180, '');
        });
    }

    /**
     * Nombre del campo desde el cual se deriva el slug cuando está vacío.
     *
     * Por default usa `name`, pero los modelos pueden sobrescribir.
     */
    protected function getSlugSource(): string
    {
        return property_exists($this, 'slugSource')
            ? $this->slugSource
            : 'name';
    }

    /**
     * Genera un slug único agregando sufijos numéricos si ya existe.
     */
    public function generateUniqueSlug(?string $value = null): string
    {
        $base = Str::limit(Str::slug($value ?? $this->{$this->getSlugSource()}), 180, '');
        $slug = $base;
        $suffix = 1;
        $column = $this->getSlugColumn();

        $query = static::query()->where(column: $column, operator: '=', value: $slug);

        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        while ($query->exists()) {
            $suffix++;
            $candidate = Str::limit($base.'-'.$suffix, 180, '');
            $query = static::query()->where(column: $column, operator: '=', value: $candidate);

            if ($this->exists) {
                $query->where($this->getKeyName(), '!=', $this->getKey());
            }

            $slug = $candidate;
        }

        return $slug;
    }

    /**
     * Nombre de la columna slug.
     */
    protected function getSlugColumn(): string
    {
        return property_exists($this, 'slugColumn')
            ? $this->slugColumn
            : 'slug';
    }
}
