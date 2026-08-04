<?php

declare(strict_types=1);

namespace Core\Tenant\Application;

use App\Models\Stores\Store;

class StoreContextService
{
    private ?Store $store = null;

    public function setStore(Store $store): void
    {
        $this->store = $store;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function getStoreId(): ?int
    {
        return $this->store?->id;
    }

    public function hasStore(): bool
    {
        return $this->store !== null;
    }

    public function clear(): void
    {
        $this->store = null;
    }
}
