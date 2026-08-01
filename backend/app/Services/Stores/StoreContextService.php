<?php

namespace App\Services\Stores;

use App\Models\Stores\Store;

/**
 * Servicio para manejar el contexto de tienda activa en la request.
 *
 * Actúa como contenedor de estado para la tienda resuelta durante
 * la lifecycle de una request. Se usa en conjunto con el middleware
 * `ResolveStoreFromDomain` que resuelve la tienda desde el dominio
 * de la request y la almacena aquí.
 *
 * Los controladores y servicios pueden inyectar este servicio para
 * acceder a la tienda actual sin necesidad de resolverla manualmente.
 *
 * Ejemplo de uso:
 * ```php
 * $store = $storeContext->getStore();
 * $storeId = $storeContext->getStoreId();
 * ```
 */
class StoreContextService
{
    private ?Store $store = null;

    /**
     * Establece la tienda activa para la request actual.
     */
    public function setStore(Store $store): void
    {
        $this->store = $store;
    }

    /**
     * Obtiene la tienda activa o null si no se ha resuelto ninguna.
     */
    public function getStore(): ?Store
    {
        return $this->store;
    }

    /**
     * Obtiene el ID de la tienda activa o null si no hay tienda.
     *
     * Útil para consultas directas sin necesidad de cargar el modelo completo.
     */
    public function getStoreId(): ?int
    {
        return $this->store?->id;
    }

    /**
     * Indica si hay una tienda activa en el contexto actual.
     */
    public function hasStore(): bool
    {
        return $this->store !== null;
    }

    /**
     * Limpia el contexto de tienda (útil en tests o between requests).
     */
    public function clear(): void
    {
        $this->store = null;
    }
}
