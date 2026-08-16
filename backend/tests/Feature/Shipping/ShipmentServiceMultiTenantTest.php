<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Exceptions\Shipping\CrossTenantShippingZoneException;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Shipping\ShipmentService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del ShipmentService relacionados con aislamiento multi-tenant.
 *
 * Valida que `createForInvoice()` rechace con `CrossTenantShippingZoneException`
 * cuando la invoice referencia una `shipping_zone` que pertenece a otra tienda.
 */
#[Group('shipping')]
#[Group('multi-tenant')]
final class ShipmentServiceMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $storeA;

    private Store $storeB;

    private Customer $customer;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    private Order $order;

    private Invoice $invoice;

    private ShippingZone $zoneFromOtherStore;

    private ShipmentService $shipments;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create();
        $this->storeA = Store::factory()->for($this->currency, 'currency')->create();
        $this->storeB = Store::factory()->for($this->currency, 'currency')->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
        $this->warehouse = Warehouse::factory()->create(attributes: ['is_active' => true]);

        $this->product = Product::factory()
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 25_00]);

        // Stock suficiente para evitar fallos en cascada.
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        // Zona que pertenece explícitamente a storeB (NO a storeA).
        $this->zoneFromOtherStore = ShippingZone::factory()->forStore($this->storeB)->create(attributes: [
            'base_cost_cents' => 5_00,
            'is_active' => true,
        ]);

        // Order + Invoice de storeA que referencia la zona de storeB.
        $address = CustomerAddress::factory()->for($this->customer)->create();
        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $this->storeA->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-CROSS',
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'total_cents' => 55_00,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 55_00,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price_cents' => 25_00,
            'discount_cents' => 0,
            'subtotal_cents' => 50_00,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);

        $this->invoice = Invoice::create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-CROSS',
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $this->zoneFromOtherStore->id,
            'total_cents' => 55_00,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price_cents' => 25_00,
            'subtotal_cents' => 50_00,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);

        $this->shipments = app(ShipmentService::class);
    }

    #[Test]
    public function it_throws_cross_tenant_exception_when_zone_belongs_to_other_store(): void
    {
        // La invoice de storeA referencia zone de storeB → fail-loud.
        $this->expectException(CrossTenantShippingZoneException::class);

        $this->shipments->createForInvoice(invoice: $this->invoice);
    }
}
