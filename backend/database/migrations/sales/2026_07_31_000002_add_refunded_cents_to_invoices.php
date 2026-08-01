<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 11: total devuelto en cents, denormalizado en la invoice.
     *
     * La fuente de verdad siempre es la suma de refunds completados. Esta
     * columna es un cache para queries rápidos (cuánto queda por devolver).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('refunded_cents')->default(0)->after('shipping_cents');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('refunded_cents');
        });
    }
};
