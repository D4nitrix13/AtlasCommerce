<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refuerza que un mismo cupón no sea usado más de una vez por el mismo
 * cliente en una misma orden. Como `order_id` es nullable (uso de prueba),
 * usamos un índice parcial de PostgreSQL solo cuando `order_id IS NOT NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX coupon_usages_unique_per_order
                 ON coupon_usages (coupon_id, customer_id, order_id)
                 WHERE order_id IS NOT NULL',
            );
        } else {
            Schema::table('coupon_usages', function (Blueprint $table): void {
                $table->unique(
                    ['coupon_id', 'customer_id', 'order_id'],
                    'coupon_usages_unique_per_order',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS coupon_usages_unique_per_order');
        } else {
            Schema::table('coupon_usages', function (Blueprint $table): void {
                $table->dropUnique('coupon_usages_unique_per_order');
            });
        }
    }
};
