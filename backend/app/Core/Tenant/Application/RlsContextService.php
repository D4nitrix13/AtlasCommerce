<?php

declare(strict_types=1);

namespace Core\Tenant\Application;

use Illuminate\Support\Facades\DB;

final class RlsContextService
{
    public function setStoreId(int $storeId): void
    {
        if (! $this->usesPostgres()) {
            return;
        }

        DB::statement(
            query: "SELECT set_config('app.current_store_id', ?, false)",
            bindings: [(string) $storeId],
        );
    }

    public function clear(): void
    {
        if (! $this->usesPostgres()) {
            return;
        }

        DB::statement(
            query: "SELECT set_config('app.current_store_id', '', false)",
        );
    }

    public function getStoreId(): ?int
    {
        if (! $this->usesPostgres()) {
            return null;
        }

        $value = DB::selectOne(
            query: "SELECT NULLIF(current_setting('app.current_store_id', true), '') AS store_id",
        )?->store_id;

        return $value === null ? null : (int) $value;
    }

    private function usesPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
