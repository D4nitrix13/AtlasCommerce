<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Payments\InvoicePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('e2e')]
final class CartToRefundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function full_checkout_flow_from_cart_to_refund(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        $customer = Customer::factory()->create();

        $product = Product::factory()->for($store->currency, 'currency')->create([
            'base_price_cents' => 25_00,
        ]);

        // 1. Cart + cart_item
        $cart = Cart::factory()
            ->for($customer)
            ->for($store)
            ->for($currency)
            ->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'cart_line_key' => 'p1',
            'quantity' => 2,
            'unit_price_cents' => $product->base_price_cents,
            'subtotal_cents' => $product->base_price_cents * 2,
        ]);

        // 2. Checkout
        $order = Order::factory()
            ->for($customer)
            ->for($store)
            ->for($currency)
            ->create([
                'subtotal_cents' => $product->base_price_cents * 2,
                'total_cents' => $product->base_price_cents * 2,
            ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price_cents' => $product->base_price_cents,
            'discount_cents' => 0,
            'subtotal_cents' => $product->base_price_cents * 2,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
        ]);

        // 3. Invoice
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal_cents' => $order->subtotal_cents,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => $order->total_cents,
            'exchange_rate_snapshot' => 1.0,
            'issued_at' => now(),
        ]);

        // 4. Pago parcial
        $method = PaymentMethod::factory()->create();
        $paidFirst = Payment::create([
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'store_id' => $store->id,
            'currency_id' => $currency->id,
            'amount_cents' => 15_00,
            'exchange_rate_snapshot' => 1.0,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $paidFirst->id,
            'applied_amount_cents' => 15_00,
            'applied_at' => now(),
        ]);

        $this->assertSame(15_00, $invoice->paidAmountCents());
        $this->assertSame(35_00, $invoice->pendingBalanceCents());

        // 5. Pago final
        $paidSecond = Payment::create([
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'store_id' => $store->id,
            'currency_id' => $currency->id,
            'amount_cents' => 35_00,
            'exchange_rate_snapshot' => 1.0,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $paidSecond->id,
            'applied_amount_cents' => 35_00,
            'applied_at' => now(),
        ]);

        $invoice->refresh();
        $this->assertSame(50_00, $invoice->paidAmountCents());
        $this->assertSame(0, $invoice->pendingBalanceCents());

        // 6. Soft-deletes preservan historial
        $order->delete();
        $this->assertNull(Order::query()->find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));

        // 7. Snapshot de moneda persiste a través de cambios
        $originalRate = $order->exchange_rate_snapshot;
        $currency->update(['is_primary' => false]);

        $order->refresh();
        $this->assertSame(expected: $originalRate, actual: $order->exchange_rate_snapshot);
    }
}
