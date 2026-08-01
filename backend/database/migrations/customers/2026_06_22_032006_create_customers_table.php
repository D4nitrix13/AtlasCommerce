<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "customers" que almacena la información de los clientes
     * de la tienda. Un cliente puede estar vinculado a un usuario (user_id)
     * o existir como cliente独立 (compras como invitado). El campo
     * "customer_type" permite diferenciar entre tipos de cliente:
     * - retail: cliente final / consumidor
     * - wholesale: cliente mayorista
     * - vip: cliente con beneficios especiales
     */
    public function up(): void
    {
        Schema::create(table: 'customers', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Relación opcional con users: permite clientes invitados sin cuenta
            $table->foreignId(column: 'user_id')
                ->nullable()
                ->constrained(table: 'users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Tipo de cliente: retail, wholesale, vip, etc.
            $table->string(column: 'customer_type', length: 50)->default(value: 'retail');
            $table->string(column: 'identity_document', length: 80)->nullable()->unique();

            $table->string(column: 'first_name', length: 80);
            $table->string(column: 'last_name', length: 80);
            $table->string(column: 'email', length: 150)->unique();
            $table->string(column: 'phone', length: 30)->nullable();

            $table->timestamp(column: 'registered_at')->useCurrent();
            $table->boolean(column: 'is_active')->default(value: true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['user_id'], name: 'customers_user_id_idx');
            $table->index(columns: ['customer_type'], name: 'customers_customer_type_idx');
            $table->index(columns: ['email'], name: 'customers_email_idx');
            $table->index(columns: ['phone'], name: 'customers_phone_idx');
            $table->index(columns: ['is_active'], name: 'customers_is_active_idx');
            $table->index(columns: ['registered_at'], name: 'customers_registered_at_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "customers" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'customers');
    }
};
