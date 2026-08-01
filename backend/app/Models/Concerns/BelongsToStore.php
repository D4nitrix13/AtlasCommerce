<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Stores\Store;
use App\Services\Stores\StoreContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla una查询 por la tienda activa resuelta por el middleware.
 *
 * Modelos que tienen `store_id` deben usar este trait. El scope global
 * agrega el filtro `store_id = <tienda actual>` automáticamente cuando
 * existe una tienda en el contexto (singleton `StoreContextService`).
 *
 * Para consultas administrativas o cross-tenant usar el helper estático
 * `Model::withoutTenantScope()` o el scope local `withoutStoreScope()`.
 *
 * Importante: este trait no define su `booted()`. Cada modelo que lo use
 * debe llamar a `static::addGlobalScope(...)` en su propio `booted()` o
 * aplicar el trait via `use BelongsToStore;` (Laravel ejecutará el
 * trait `bootBelongsToStore` automáticamente gracias a la convención).
 */
trait BelongsToStore
{
    /**
     * Hook de boot del trait — aplica el global scope.
     *
     * Laravel invoca `boot<TraitName>` automáticamente al cargar
     * un modelo que use este trait.
     */
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder): void {
            $storeId = app(StoreContextService::class)->getStoreId();

            if ($storeId !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.store_id',
                    $storeId,
                );
            }
        });
    }

    /**
     * Scope local para forzar una tienda específica (override del contexto).
     *
     * Quita el global scope y aplica un filtro explícito: útil para
     * tareas administrativas o cross-tenant puntuales.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query
            ->withoutGlobalScope('store')
            ->where($query->getModel()->getTable().'.store_id', $storeId);
    }

    /**
     * Scope local para saltar el global scope (uso administrativo).
     *
     * Ej.: `Order::query()->withoutStoreScope()->get()` devuelve todas las
     * órdenes de todas las tiendas.
     */
    public function scopeWithoutStoreScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('store');
    }

    /**
     * Helper estático equivalente a `Model::withoutGlobalScope('store')`.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope('store');
    }

    /**
     * Relación a la tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
