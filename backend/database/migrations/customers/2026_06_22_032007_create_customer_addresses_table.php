<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla "customer_addresses" para almacenar las direcciones
     * de envío y facturación de cada cliente. Un cliente puede tener
     * múltiples direcciones, marcando una como principal (is_primary).
     * El campo "address_type" distingue entre direcciones de envío
     * y facturación.
     */
    public function up(): void
    {
        Schema::create(table: 'customer_addresses', callback: function (Blueprint $table): void {
            $table->id(column: 'id');

            // Eliminación en cascada: si se elimina el cliente, se eliminan sus direcciones
            $table->foreignId(column: 'customer_id')
                ->constrained(table: 'customers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string(column: 'address_type', length: 50)->default(value: 'shipping');
            $table->string(column: 'country', length: 80)->default(value: 'Nicaragua');
            $table->string(column: 'department', length: 100)->nullable();
            $table->string(column: 'municipality', length: 100)->nullable();
            $table->text(column: 'detailed_address');
            $table->text(column: 'reference')->nullable();
            $table->boolean(column: 'is_primary')->default(value: false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(columns: ['customer_id'], name: 'customer_addresses_customer_id_idx');
            $table->index(columns: ['address_type'], name: 'customer_addresses_address_type_idx');
            $table->index(columns: ['country'], name: 'customer_addresses_country_idx');
            $table->index(columns: ['department'], name: 'customer_addresses_department_idx');
            $table->index(columns: ['municipality'], name: 'customer_addresses_municipality_idx');
            $table->index(columns: ['is_primary'], name: 'customer_addresses_is_primary_idx');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla "customer_addresses" y todas sus dependencias.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'customer_addresses');
    }
};
