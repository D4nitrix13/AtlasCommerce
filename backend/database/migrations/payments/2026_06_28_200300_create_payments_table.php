<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('provider', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'payment_methods_is_active_idx');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->decimal('exchange_rate_snapshot', 18, 8);

            $table->string('reference', 160)->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status', 'paid_at'], 'payments_store_status_date_idx');
            $table->index(['customer_id', 'status'], 'payments_customer_status_idx');
            $table->index('reference', 'payments_reference_idx');
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('applied_amount_cents');
            $table->timestamp('applied_at')->useCurrent();

            $table->timestamps();

            $table->index(['invoice_id', 'applied_at'], 'invoice_payments_invoice_applied_idx');
            $table->index('payment_id', 'invoice_payments_payment_id_idx');
        });

        Schema::create('payment_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->unsignedBigInteger('scheduled_amount_cents');
            $table->unsignedBigInteger('paid_amount_cents')->default(0);
            $table->date('due_date');
            $table->string('status', 50)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'installment_number'], 'payment_installments_invoice_number_idx');
            $table->index(['status', 'due_date'], 'payment_installments_status_due_idx');
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('payment_provider', 80);
            $table->string('transaction_code', 160)->nullable();
            $table->longText('provider_response')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('transacted_at')->useCurrent();
            $table->timestamps();

            $table->index(['payment_id', 'status'], 'payment_transactions_payment_status_idx');
            $table->index('transaction_code', 'payment_transactions_transaction_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_installments');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
