<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            // Porcentaje de impuesto aplicado al subtotal del carrito en el
            // checkout. 0.00 = exento. Rango válido: 0.00 a 100.00.
            // Para MVP se aplica un solo porcentaje por tienda; el esquema
            // `taxes` + `product_tax` queda disponible para reglas por
            // producto en una fase futura.
            $table->decimal('tax_rate', 5, 2)
                ->default(0)
                ->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('tax_rate');
        });
    }
};
