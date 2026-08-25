<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convierte `base_cost` (decimal(12,2)) a `base_cost_cents` (bigint unsigned)
        // para alinear con el resto del schema monetario (PHASE 1).
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->unsignedBigInteger('base_cost_cents')->default(0)->after('description');
        });

        if (Schema::hasColumn('shipping_zones', 'base_cost')) {
            // Migrar valores existentes: 12.34 → 1234.
            DB::statement(
                'UPDATE shipping_zones
                 SET base_cost_cents = (base_cost * 100)::bigint
                 WHERE base_cost IS NOT NULL',
            );

            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->dropColumn('base_cost');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->decimal('base_cost', 12, 2)->default(0)->after('description');
        });

        if (Schema::hasColumn('shipping_zones', 'base_cost_cents')) {
            DB::statement(
                'UPDATE shipping_zones
                 SET base_cost = base_cost_cents / 100.0',
            );

            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->dropColumn('base_cost_cents');
            });
        }
    }
};
