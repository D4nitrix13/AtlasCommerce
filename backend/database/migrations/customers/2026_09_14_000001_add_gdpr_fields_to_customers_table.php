<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade campos GDPR al modelo Customer.
 *
 * RF-CUST-042 — Exportar y borrar datos del cliente (GDPR art. 15 + 17,
 * Ley 896 de Protección de Datos Personales Nicaragua).
 *
 * Campos añadidos:
 *  - anonymized_at: timestamp en que se completó la anonimización
 *    (PII reemplazada por placeholders no identificables).
 *  - deletion_requested_at: timestamp de la primera solicitud de borrado,
 *    independiente de cuándo se confirme.
 *  - deletion_token_hash: SHA-256 del token entregado al cliente para
 *    confirmar el borrado. Nunca se guarda el token en claro.
 *  - deletion_token_expires_at: ventana de gracia (default 24h) para que
 *    el cliente confirme. Pasado el plazo, el token caduca.
 *
 * Decisiones:
 *  - El token se hashea (no se guarda plaintext) por si la BD se ve
 *    comprometida: el atacante no puede reusar el token aunque robe
 *    el hash.
 *  - `deletion_requested_at` se persiste apenas se pide el borrado,
 *    aunque todavía no se haya confirmado; sirve de auditoría legal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'customers', callback: function (Blueprint $table): void {
            $table->timestamp(column: 'anonymized_at')->nullable()->after(column: 'is_active');
            $table->timestamp(column: 'deletion_requested_at')->nullable()->after(column: 'anonymized_at');
            $table->string(column: 'deletion_token_hash', length: 64)->nullable()->after(column: 'deletion_requested_at');
            $table->timestamp(column: 'deletion_token_expires_at')->nullable()->after(column: 'deletion_token_hash');

            $table->index(columns: ['anonymized_at'], name: 'customers_anonymized_at_idx');
            $table->index(columns: ['deletion_requested_at'], name: 'customers_deletion_requested_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table(table: 'customers', callback: function (Blueprint $table): void {
            $table->dropIndex(indexes: ['customers_anonymized_at_idx']);
            $table->dropIndex(indexes: ['customers_deletion_requested_at_idx']);

            $table->dropColumn(columns: [
                'anonymized_at',
                'deletion_requested_at',
                'deletion_token_hash',
                'deletion_token_expires_at',
            ]);
        });
    }
};
