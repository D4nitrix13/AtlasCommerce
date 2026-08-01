<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\InvoicePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('sales')]
final class MoneyFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_stores_money_in_cents_with_currency_snapshot(): void
    {
        $order = $this->makeOrder(totalCents: 50000);

        $this->assertSame(50000, $order->total_cents);
        $this->assertNotNull($order->currency_id);
        $this->assertNotNull($order->exchange_rate_snapshot);
    }

    #[Test]
    public function invoice_paid_balance_is_derived_from_payments_pivot_not_columns(): void
    {
        $order = $this->makeOrder(totalCents: 100_000);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'store_id' => $order->store_id,
            'currency_id' => $order->currency_id,
            'invoice_number' => 'INV-001',
            'subtotal_cents' => 90_000,
            'discount_cents' => 0,
            'tax_cents' => 10_000,
            'total_cents' => 100_000,
            'exchange_rate_snapshot' => 1.0,
            'issued_at' => now(),
        ]);

        $method = PaymentMethod::create([
            'code' => 'cash',
            'name' => 'Efectivo',
            'is_active' => true,
        ]);

        $payment1 = Payment::create([
            'customer_id' => $order->customer_id,
            'payment_method_id' => $method->id,
            'store_id' => $order->store_id,
            'currency_id' => $order->currency_id,
            'amount_cents' => 60_000,
            'exchange_rate_snapshot' => 1.0,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment1->id,
            'applied_amount_cents' => 60_000,
            'applied_at' => now(),
        ]);

        $this->assertSame(60_000, $invoice->paidAmountCents());
        $this->assertSame(40_000, $invoice->pendingBalanceCents());

        // No existen columnas denormalizadas: paid_amount / pending_balance
        // deben devolver null al intentar accederlas y NUNCA escribir.
        $this->assertFalse(
            Schema::hasColumn('invoices', 'paid_amount'),
            'La columna paid_amount debe estar eliminada.'
        );
        $this->assertFalse(
            Schema::hasColumn('invoices', 'pending_balance'),
            'La columna pending_balance debe estar eliminada.'
        );
    }

    #[Test]
    public function money_value_object_converts_cents_to_decimal_without_loss(): void
    {
        $cents = Money::fromDecimal('19.99', 2);

        $this->assertSame(1999, $cents->cents);
        $this->assertSame('19.99', $cents->toDecimal());
    }

    #[Test]
    public function currency_decimal_places_drives_parsing(): void
    {
        $jpy = Currency::create([
            'code' => 'JPY',
            'name' => 'Yen Japonés',
            'symbol' => '¥',
            'decimal_places' => 0,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $yen = Money::fromDecimal('1500', scale: $jpy->decimal_places);

        $this->assertSame(1500, $yen->cents);
    }

    private function makeOrder(int $totalCents): Order
    {
        $currency = Currency::create([
            'code' => 'NIO',
            'name' => 'Córdoba Nicaragüense',
            'symbol' => 'C$',
            'decimal_places' => 2,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $store = Store::create([
            'currency_id' => $currency->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'is_active' => true,
        ]);

        $channel = SalesChannel::create([
            'store_id' => $store->id,
            'code' => 'web',
            'name' => 'Web',
            'type' => 'online',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'currency_id' => $currency->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'base_price_cents' => $totalCents,
            'cost_cents' => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'email' => 'buyer@test.test',
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'address_type' => 'shipping',
            'country' => 'Nicaragua',
            'municipality' => 'Managua',
            'detailed_address' => 'Test 123',
            'is_primary' => true,
        ]);

        $cart = Cart::create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'currency_id' => $currency->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'cart_line_key' => 'test-line',
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'subtotal_cents' => $totalCents,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $store->id,
            'sales_channel_id' => $channel->id,
            'user_creator_id' => $user->id,
            'currency_id' => $currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => $totalCents,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => $totalCents,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => $totalCents,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'discount_cents' => 0,
            'subtotal_cents' => $totalCents,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
        ]);

        return $order;
    }
}
