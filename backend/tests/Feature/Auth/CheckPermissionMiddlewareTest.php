<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Payments\PaymentService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
#[Group('rbac')]
final class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private Warehouse $warehouse;

    private Product $product;

    private Order $order;

    private Invoice $invoice;

    private ShippingZone $zone;

    private PaymentMethod $paymentMethod;

    private Role $adminRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create(attributes: [
            'code' => 'USD',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create();
        $this->customer = Customer::factory()->create();
        $this->warehouse = Warehouse::factory()->create(attributes: ['is_active' => true]);
        $this->zone = ShippingZone::factory()->create(attributes: [
            'base_cost_cents' => 5_00,
            'is_active' => true,
        ]);
        $this->paymentMethod = PaymentMethod::factory()->create();
        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['base_price_cents' => 25_00]);

        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->seedOrderAndInvoice();

        app(StoreContextService::class)->setStore($this->store);

        // Roles: admin (bypasea) y staff (necesita permisos explícitos).
        $this->adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->staffRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'staff'],
            values: ['name' => 'Operador', 'is_active' => true],
        );

        $shipPermission = Permission::query()->updateOrCreate(
            attributes: ['slug' => 'shipping.shipments.ship'],
            values: ['name' => 'Ship Shipments', 'module' => 'shipping'],
        );

        $this->staffRole->permissions()->sync([$shipPermission->id]);
    }

    #[Test]
    public function admin_role_bypasses_permission_check(): void
    {
        $admin = User::factory()->create(attributes: ['role_id' => $this->adminRole->id, 'is_active' => true]);
        Sanctum::actingAs($admin);

        // Liquida la invoice para crear un shipment.
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        $response = $this->postJson("/api/shipments/{$shipment->id}/ship");
        $response->assertOk();
    }

    #[Test]
    public function staff_with_permission_passes_check(): void
    {
        $staff = User::factory()->create(attributes: ['role_id' => $this->staffRole->id, 'is_active' => true]);
        Sanctum::actingAs($staff);

        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        $response = $this->postJson("/api/shipments/{$shipment->id}/ship");
        $response->assertOk();
    }

    #[Test]
    public function staff_without_permission_is_forbidden(): void
    {
        $staff = User::factory()->create(attributes: ['role_id' => $this->staffRole->id, 'is_active' => true]);
        Sanctum::actingAs($staff);

        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        // El permiso `shipping.shipments.deliver` NO está asignado al rol staff.
        $response = $this->postJson("/api/shipments/{$shipment->id}/deliver");
        $response->assertStatus(403);
        $response->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'shipping.shipments.deliver'));
    }

    #[Test]
    public function user_without_role_is_forbidden(): void
    {
        $noRole = User::factory()->create(attributes: ['role_id' => null, 'is_active' => true]);
        Sanctum::actingAs($noRole);

        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        $response = $this->postJson("/api/shipments/{$shipment->id}/ship");
        $response->assertStatus(403);
    }

    #[Test]
    public function inactive_user_is_forbidden_even_with_admin_role(): void
    {
        $admin = User::factory()->create(attributes: ['role_id' => $this->adminRole->id, 'is_active' => false]);
        Sanctum::actingAs($admin);

        $shipment = Shipment::factory()->create(attributes: [
            'order_id' => $this->order->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/shipments/{$shipment->id}/ship");
        $response->assertStatus(403);
        $response->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'desactivada'));
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/shipments/1/ship');
        $response->assertStatus(401);
    }

    #[Test]
    public function user_with_inactive_role_is_forbidden(): void
    {
        $this->staffRole->update(['is_active' => false]);

        $staff = User::factory()->create(attributes: ['role_id' => $this->staffRole->id, 'is_active' => true]);
        Sanctum::actingAs($staff);

        $shipment = Shipment::factory()->create(attributes: [
            'order_id' => $this->order->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/shipments/{$shipment->id}/ship");
        $response->assertStatus(403);
    }

    #[Test]
    public function has_permission_method_returns_correctly(): void
    {
        $admin = User::factory()->create(attributes: ['role_id' => $this->adminRole->id]);
        $staff = User::factory()->create(attributes: ['role_id' => $this->staffRole->id]);

        $this->assertTrue($admin->hasPermission(slug: 'qualquier.permiso'));
        $this->assertTrue($staff->hasPermission(slug: 'shipping.shipments.ship'));
        $this->assertFalse($staff->hasPermission(slug: 'shipping.shipments.deliver'));
        $this->assertFalse($staff->hasPermission(slug: 'auth.users.manage'));
    }

    private function seedOrderAndInvoice(): void
    {
        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $this->zone->id,
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
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $this->zone->id,
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
    }
}
