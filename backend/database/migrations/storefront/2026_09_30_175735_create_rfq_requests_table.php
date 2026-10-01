<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla para solicitudes de cotización (RFQ) que el cliente
     * envía desde el storefront para pedidos grandes / B2B.
     *
     * El ticket RFQ-{timestamp36} es el identificador público visible
     * en el email y en el panel admin. El `id` interno se mantiene
     * autoincrement.
     */
    public function up(): void
    {
        Schema::create(table: 'rfq_requests', callback: function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'public_id', length: 30)->unique();

            $table->foreignId(column: 'store_id')
                ->nullable()
                ->constrained(table: 'stores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId(column: 'customer_id')
                ->nullable()
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string(column: 'contact_name', length: 200);
            $table->string(column: 'contact_email', length: 200);
            $table->string(column: 'contact_phone', length: 50)->nullable();
            $table->string(column: 'company_name', length: 200)->nullable();

            $table->string(column: 'subject', length: 200);
            $table->text(column: 'message');

            // JSON serializado: array de { product_id, variant_id?, quantity, notes? }
            $table->json(column: 'items')->nullable();

            // Estado: pending, quoted, accepted, rejected
            $table->string(column: 'status', length: 30)->default(value: 'pending');

            // Total estimado enviado por el cliente (en centavos de la moneda del store)
            $table->bigInteger(column: 'estimated_total_cents')->nullable();
            $table->string(column: 'currency_code', length: 3)->nullable();

            $table->timestamps();

            $table->index(columns: ['store_id', 'status'], name: 'rfq_store_status_idx');
            $table->index(columns: ['contact_email'], name: 'rfq_contact_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'rfq_requests');
    }
};
