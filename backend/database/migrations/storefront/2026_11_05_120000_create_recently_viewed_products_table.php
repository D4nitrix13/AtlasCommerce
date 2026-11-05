<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos vistos recientemente en la PDP, persistidos por
     * identidad: `user_id` (cliente autenticado) o `guest_token`
     * (visitante, token opaco del frontend). Nunca ambos — cada fila
     * lleva exactamente una de las dos identidades.
     *
     * Los dos UNIQUE son planos (sin `viewed_at`) a propósito: el
     * upsert del servicio matchea por identidad + producto, y en
     * Postgres los NULL no colisionan, así que las filas guest
     * (`user_id NULL`) no interfieren con las de usuario
     * (`guest_token NULL`) ni entre sí (las separa el segundo UNIQUE).
     * Los índices `(identidad, viewed_at)` cubren el orden del listado.
     */
    public function up(): void
    {
        Schema::create(table: 'recently_viewed_products', callback: function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string(column: 'guest_token', length: 64)->nullable();

            $table->foreignId(column: 'product_id')
                ->constrained(table: 'products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamp(column: 'viewed_at');
            $table->timestamps();

            $table->unique(columns: ['user_id', 'product_id'], name: 'rvp_user_product_uniq');
            $table->unique(columns: ['guest_token', 'product_id'], name: 'rvp_guest_product_uniq');
            $table->index(columns: ['user_id', 'viewed_at'], name: 'rvp_user_viewed_idx');
            $table->index(columns: ['guest_token', 'viewed_at'], name: 'rvp_guest_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'recently_viewed_products');
    }
};
