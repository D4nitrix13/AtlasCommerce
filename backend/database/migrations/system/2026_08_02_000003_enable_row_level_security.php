<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'audits',
        'carts',
        'coupons',
        'erp_connections',
        'customer_stores',
        'invoices',
        'notifications',
        'orders',
        'payments',
        'product_questions',
        'product_reviews',
        'product_stores',
        'promotions',
        'refunds',
        'return_requests',
        'sales_channels',
        'store_features',
        'store_settings',
        'store_social_links',
        'store_themes',
        'support_tickets',
        'user_stores',
        'wholesale_prices',
        'wishlists',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            $policy = $table.'_tenant_isolation_policy';
            $quotedTable = '"'.$table.'"';
            $quotedPolicy = '"'.$policy.'"';
            $expression = '"store_id" = NULLIF(current_setting('
                ."'app.current_store_id', true), '')::bigint";

            DB::statement("ALTER TABLE {$quotedTable} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$quotedTable} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$quotedPolicy} ON {$quotedTable}");
            DB::statement(
                "CREATE POLICY {$quotedPolicy} ON {$quotedTable} "
                ."USING ({$expression}) WITH CHECK ({$expression})",
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            $policy = $table.'_tenant_isolation_policy';
            $quotedTable = '"'.$table.'"';
            $quotedPolicy = '"'.$policy.'"';

            DB::statement("DROP POLICY IF EXISTS {$quotedPolicy} ON {$quotedTable}");
            DB::statement("ALTER TABLE {$quotedTable} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$quotedTable} DISABLE ROW LEVEL SECURITY");
        }
    }
};
