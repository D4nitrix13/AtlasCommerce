<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Carts\Wishlist;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de 2 customers de test con orders de ejemplo, uno por tienda
 * (panda-estampados B2B, kitsune B2C). Cubre el caso de uso del
 * customer-auth sprint (CA.5 follow-up) — el Account dashboard del
 * frontend ahora tiene data real para mostrar.
 *
 * Tabla:
 *
 * | Email                              | Tienda           | Tipo       | Orders |
 * | ---------------------------------- | ---------------- | ---------- | ------ |
 * | cliente@panda-estampados.test      | panda-estampados  | wholesale  | 3      |
 * | cliente@kitsune.test              | kitsune          | retail     | 2      |
 *
 * El user se crea con role='customer' (CA.1) y el Customer profile
 * se vincula via customer.user_id (CA.2). El Customer se asocia a la
 * tienda via customer_stores pivot. Cada customer tiene 2-3 orders
 * con distintos status para que el dashboard del account muestre la
 * variedad completa (pending, processing, delivered, etc).
 *
 * Currency: los orders usan la currency de su tienda
 * (panda-estampados = NIO, kitsune = USD). El seeder asume que
 * `DefaultAdminSeeder` (que crea las currencies y los stores)
 * corrió antes. Si no, los stores y currencies se crean al vuelo.
 *
 * Idempotente: re-correr no duplica users, customers, ni orders.
 *
 * Uso:
 *   php artisan db:seed --class=CustomerTestSeeder
 *
 * Credenciales: ambos customers tienen password 'password' (mismo
 * convenio que admin@<slug>.com en DefaultAdminSeeder).
 */
final class CustomerTestSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();

        $panda = $this->ensureStore(
            slug: 'panda-estampados',
            name: 'Panda Estampados',
            currencyCode: 'NIO',
        );

        $kitsune = $this->ensureStore(
            slug: 'kitsune',
            name: 'Kitsune Store',
            currencyCode: 'USD',
        );

        $this->seedCustomer(
            role: $customerRole,
            store: $panda,
            email: 'cliente@panda-estampados.test',
            firstName: 'Cliente',
            lastName: 'Panda',
            customerType: 'wholesale',
            phone: '+505 8888 1234',
            productSku: 'SEED-PANDA-001',
            orders: [
                ['order_number' => 'ORD-PD-1001', 'status' => 'completed', 'total_cents' => 145_00, 'days_ago' => 14],
                ['order_number' => 'ORD-PD-1002', 'status' => 'processing', 'total_cents' => 28_500, 'days_ago' => 3],
                ['order_number' => 'ORD-PD-1003', 'status' => 'pending', 'total_cents' => 5_200, 'days_ago' => 0],
            ],
        );

        $this->seedCustomer(
            role: $customerRole,
            store: $kitsune,
            email: 'cliente@kitsune.test',
            firstName: 'Cliente',
            lastName: 'Kitsune',
            customerType: 'retail',
            phone: '+505 8888 5678',
            productSku: 'SEED-KITSUNE-001',
            orders: [
                ['order_number' => 'ORD-KT-2001', 'status' => 'completed', 'total_cents' => 12_300, 'days_ago' => 5],
                ['order_number' => 'ORD-KT-2002', 'status' => 'pending', 'total_cents' => 4_500, 'days_ago' => 1],
            ],
        );
    }

    private function ensureStore(string $slug, string $name, string $currencyCode): Store
    {
        $currency = Currency::query()->firstOrCreate(
            attributes: ['code' => $currencyCode],
            values: [
                'name' => $currencyCode === 'NIO' ? 'Córdoba Nicaragüense' : 'US Dollar',
                'symbol' => $currencyCode === 'NIO' ? 'C$' : '$',
                'decimal_places' => 2,
                'is_primary' => $currencyCode === 'NIO',
                'is_active' => true,
            ],
        );

        return Store::query()->firstOrCreate(
            attributes: ['slug' => $slug],
            values: [
                'name' => $name,
                'currency_id' => $currency->id,
                'is_active' => true,
            ],
        );
    }

    private function seedCustomer(
        Role $role,
        Store $store,
        string $email,
        string $firstName,
        string $lastName,
        string $customerType,
        string $phone,
        string $productSku,
        array $orders,
    ): void {
        // Idempotente: si el user ya existe (de un run anterior), lo
        // actualizamos en vez de fallar por email duplicado.
        $user = User::query()->updateOrCreate(
            attributes: ['email' => $email],
            values: [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make(value: self::DEFAULT_PASSWORD),
                'phone' => $phone,
                'role_id' => $role->id,
                'is_active' => true,
                'email_verified_at' => now(),
                'registered_at' => now(),
            ],
        );

        $customer = Customer::query()->updateOrCreate(
            attributes: ['user_id' => $user->id],
            values: [
                'customer_type' => $customerType,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'is_active' => true,
                'registered_at' => $user->registered_at,
            ],
        );

        // Vincula a la tienda (idempotente via syncWithoutDetaching).
        $customer->stores()->syncWithoutDetaching(
            ids: [$store->id],
        );

        // Cada customer tiene una wishlist vacía para que el link
        // "Wishlist" del dashboard funcione (F2.2 spec §4.5).
        Wishlist::query()->firstOrCreate(
            attributes: ['customer_id' => $customer->id],
            values: ['name' => 'Default wishlist'],
        );

        // Crear un Product mínimo por customer para que el OrderItem
        // pueda referenciarlo (FK required). Usamos updateOrCreate
        // para idempotencia si el seeder corre 2 veces.
        $currency = Currency::query()->find($store->currency_id);
        $category = Category::query()->firstOrCreate(
            attributes: ['slug' => 'seed-category'],
            values: ['name' => 'Seed Category', 'is_active' => true],
        );
        $brand = Brand::query()->firstOrCreate(
            attributes: ['slug' => 'seed-brand'],
            values: ['name' => 'Seed Brand', 'is_active' => true],
        );
        $product = Product::query()->updateOrCreate(
            attributes: ['sku' => $productSku],
            values: [
                'name' => "Sample product ({$firstName})",
                // Slug incluye el SKU para garantizar unicidad entre los
                // 2 customers seedeados (Panda y Kitsune). Si no, ambos
                // colisionan en 'sample-product-cliente' y el segundo
                // create falla por unique constraint.
                'slug' => 'sample-product-'.strtolower(string: $productSku),
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_id' => $currency->id,
                'base_price_cents' => 10_000,
                'is_active' => true,
                'published_at' => now(),
            ],
        );
        // Asocia el product a la tienda via product_stores. Usamos
        // ProductStore::firstOrCreate (no syncWithoutDetaching) porque
        // la relation es con ProductStore model, no Store — el
        // pivot key default es el id de ProductStore, no de Store.
        ProductStore::query()->firstOrCreate(
            attributes: [
                'product_id' => $product->id,
                'store_id' => $store->id,
            ],
            values: [
                'slug' => $product->slug,
                'is_visible' => true,
            ],
        );

        $channel = SalesChannel::query()->firstOrCreate(
            attributes: ['store_id' => $store->id, 'name' => 'web'],
            values: ['type' => 'web', 'is_active' => true],
        );

        $address = CustomerAddress::query()->updateOrCreate(
            attributes: ['customer_id' => $customer->id, 'is_primary' => true],
            values: [
                'address_type' => 'shipping',
                'country' => 'NI',
                'department' => 'Managua',
                'municipality' => 'Managua',
                'detailed_address' => 'Av. Prueba 123',
                'reference' => null,
            ],
        );

        foreach ($orders as $orderData) {
            // Idempotente: si la order_number ya existe, skip.
            $existing = Order::query()
                ->where(column: 'order_number', operator: '=', value: $orderData['order_number'])
                ->first();
            if ($existing !== null) {
                continue;
            }

            $orderedAt = now()->subDays(value: $orderData['days_ago']);
            $order = Order::query()->create(attributes: [
                'customer_id' => $customer->id,
                'shipping_address_id' => $address->id,
                'store_id' => $store->id,
                'sales_channel_id' => $channel->id,
                'currency_id' => $currency->id,
                'order_number' => $orderData['order_number'],
                'subtotal_cents' => $orderData['total_cents'],
                'discount_cents' => 0,
                'tax_cents' => 0,
                'shipping_cents' => 0,
                'total_cents' => $orderData['total_cents'],
                'exchange_rate_snapshot' => 1.0,
                'total_in_base_currency_cents' => $orderData['total_cents'],
                'status' => $orderData['status'],
                'ordered_at' => $orderedAt,
            ]);

            // Cada order tiene 1 item que referencia el Product seed.
            // El snapshot del name/sku es para que la order sobreviva
            // si el product se borra después.
            OrderItem::query()->create(attributes: [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'quantity' => 1,
                'unit_price_cents' => $orderData['total_cents'],
                'discount_cents' => 0,
                'subtotal_cents' => $orderData['total_cents'],
            ]);
        }
    }
}
