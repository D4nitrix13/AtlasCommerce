<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Core\Tenant\Application\RlsContextService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('architecture')]
#[Group('rls')]
final class RlsPolicyTest extends TestCase
{
    #[Test]
    public function rls_context_sets_and_clears_the_postgres_session_variable(): void
    {
        $this->requirePostgres();
        $context = app(RlsContextService::class);

        try {
            $context->setStoreId(storeId: 987654);

            self::assertSame(987654, $context->getStoreId());
        } finally {
            $context->clear();
        }

        self::assertNull($context->getStoreId());
    }

    #[Test]
    public function tenant_tables_have_forced_rls_and_a_policy(): void
    {
        $this->requirePostgres();

        $table = DB::selectOne(
            query: "SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = 'orders'",
        );
        $policy = DB::selectOne(
            query: "SELECT policyname FROM pg_policies WHERE tablename = 'orders'",
        );

        self::assertTrue((bool) $table?->relrowsecurity);
        self::assertTrue((bool) $table?->relforcerowsecurity);
        self::assertSame('orders_tenant_isolation_policy', $policy?->policyname);

        // El plugin ERP puede no estar instalado en este entorno
        // (depende de si el package del plugin está activo en
        // composer). Si la tabla no existe, skip la verificación de RLS
        // del plugin — no tiene sentido probar algo que no aplica.
        $pluginTable = DB::selectOne(
            query: "SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = 'erp_connections'",
        );
        if ($pluginTable === null) {
            self::markTestSkipped('Plugin ERP no instalado en este entorno (tabla erp_connections no existe).');
        }

        $pluginPolicy = DB::selectOne(
            query: "SELECT policyname FROM pg_policies WHERE tablename = 'erp_connections'",
        );

        self::assertTrue((bool) $pluginTable?->relrowsecurity);
        self::assertTrue((bool) $pluginTable?->relforcerowsecurity);
        self::assertSame(
            'erp_connections_tenant_isolation_policy',
            $pluginPolicy?->policyname,
        );
    }

    #[Test]
    public function domain_resolution_table_remains_available_without_tenant_context(): void
    {
        $this->requirePostgres();

        $table = DB::selectOne(
            query: "SELECT relrowsecurity FROM pg_class WHERE relname = 'store_domains'",
        );

        self::assertFalse((bool) $table?->relrowsecurity);
    }

    private function requirePostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self->markTestSkipped('RLS tests require PostgreSQL.');
        }
    }
}
