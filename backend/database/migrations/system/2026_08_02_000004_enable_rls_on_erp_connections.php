<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLE = 'erp_connections';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $table = '"'.self::TABLE.'"';
        $policy = '"'.self::TABLE.'_tenant_isolation_policy"';
        $expression = '"store_id" = NULLIF(current_setting('
            ."'app.current_store_id', true), '')::bigint";

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        DB::statement(
            "CREATE POLICY {$policy} ON {$table} "
            ."USING ({$expression}) WITH CHECK ({$expression})",
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $table = '"'.self::TABLE.'"';
        $policy = '"'.self::TABLE.'_tenant_isolation_policy"';

        DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
};
