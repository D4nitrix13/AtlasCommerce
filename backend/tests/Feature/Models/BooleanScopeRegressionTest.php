<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Auth\Role;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\ProductAttribute;
use App\Models\Catalog\ProductImage;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\Promotion;
use App\Models\Purchases\Supplier;
use App\Models\Settings\Currency;
use App\Models\Shipping\ShippingCompany;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreFeature;
use App\Models\Support\ProductReview;
use App\Models\System\Notification;
use App\Models\Taxes\Tax;
use App\Models\Carts\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression del bug: `where(column: 'X', value: true)` con named-args en
 * columnas boolean (cast) generaba `X IS NULL` sin binding en PostgreSQL.
 *
 * Cubre cada scope/uso afectado para asegurar que ahora genera el SQL
 * correcto Y devuelve los registros esperados (no filas NULL ni falsos).
 */
#[Group('regression')]
#[Group('boolean-scope')]
final class BooleanScopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function promotion_scopeValid_filters_by_is_active_true_not_null(): void
    {
        Promotion::factory()->create(['is_active' => true]);
        Promotion::factory()->create(['is_active' => false]);

        $count = Promotion::query()->valid()->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function coupon_scopeValid_filters_by_is_active_true_not_null(): void
    {
        Coupon::factory()->create(['is_active' => true]);
        Coupon::factory()->create(['is_active' => false]);

        $count = Coupon::query()->valid()->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function category_scopeActive_filters_correctly(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $this->assertSame(1, Category::query()->active()->count());
    }

    #[Test]
    public function brand_scopeActive_filters_correctly(): void
    {
        Brand::factory()->create(['is_active' => true]);
        Brand::factory()->create(['is_active' => false]);

        $this->assertSame(1, Brand::query()->active()->count());
    }

    #[Test]
    public function role_scopeActive_filters_correctly(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();

        Role::query()->create(['store_id' => $store->id, 'slug' => 'role-a', 'name' => 'Role A', 'is_active' => true]);
        Role::query()->create(['store_id' => $store->id, 'slug' => 'role-b', 'name' => 'Role B', 'is_active' => false]);

        $this->assertSame(1, Role::query()->active()->count());
    }

    #[Test]
    public function tax_scopeActive_filters_correctly(): void
    {
        Tax::query()->create(['name' => 'IVA', 'percentage' => 15.00, 'is_active' => true]);
        Tax::query()->create(['name' => 'Old', 'percentage' => 10.00, 'is_active' => false]);

        $this->assertSame(1, Tax::query()->active()->count());
    }

    #[Test]
    public function customer_scopeActive_filters_correctly(): void
    {
        Customer::factory()->create(['is_active' => true]);
        Customer::factory()->create(['is_active' => false]);

        $this->assertSame(1, Customer::query()->active()->count());
    }

    #[Test]
    public function customer_address_scopePrimary_filters_correctly(): void
    {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->for($customer)->create(['is_primary' => true]);
        CustomerAddress::factory()->for($customer)->create(['is_primary' => false]);

        $this->assertSame(1, CustomerAddress::query()->primary()->count());
    }

    #[Test]
    public function shipping_company_scopeActive_filters_correctly(): void
    {
        ShippingCompany::factory()->create(['is_active' => true]);
        ShippingCompany::factory()->create(['is_active' => false]);

        $this->assertSame(1, ShippingCompany::query()->active()->count());
    }

    #[Test]
    public function supplier_scopeActive_filters_correctly(): void
    {
        Supplier::query()->create(['name' => 'A', 'is_active' => true]);
        Supplier::query()->create(['name' => 'B', 'is_active' => false]);

        $this->assertSame(1, Supplier::query()->active()->count());
    }

    #[Test]
    public function wishlist_scopeDefault_filters_correctly(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        $customer = Customer::factory()->create();

        Wishlist::factory()->for($customer)->for($store)->create(['is_default' => true]);
        Wishlist::factory()->for($customer)->for($store)->create(['is_default' => false]);

        $this->assertSame(1, Wishlist::query()->default()->count());
    }

    #[Test]
    public function product_attribute_scopes_filter_correctly(): void
    {
        ProductAttribute::query()->create(['slug' => 'a', 'name' => 'A', 'is_filterable' => true, 'is_required' => true]);
        ProductAttribute::query()->create(['slug' => 'b', 'name' => 'B', 'is_filterable' => false, 'is_required' => false]);

        $this->assertSame(1, ProductAttribute::query()->filterable()->count());
        $this->assertSame(1, ProductAttribute::query()->required()->count());
    }

    #[Test]
    public function product_image_scopePrimary_filters_correctly(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        $category = Category::factory()->create();
        $product = \App\Models\Catalog\Product::factory()
            ->for($category, 'category')
            ->for($currency, 'currency')
            ->create();

        ProductImage::query()->create(['product_id' => $product->id, 'image_url' => 'a.jpg', 'is_primary' => true]);
        ProductImage::query()->create(['product_id' => $product->id, 'image_url' => 'b.jpg', 'is_primary' => false]);

        $this->assertSame(1, ProductImage::query()->primary()->count());
    }

    #[Test]
    public function product_review_scope_approved_and_pending(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        $category = Category::factory()->create();
        $product = \App\Models\Catalog\Product::factory()
            ->for($category, 'category')
            ->for($currency, 'currency')
            ->create();
        $customer = Customer::factory()->create();

        ProductReview::factory()->for($product)->for($customer)->create(['is_approved' => true]);
        ProductReview::factory()->for($product)->for($customer)->create(['is_approved' => false]);

        $this->assertSame(1, ProductReview::query()->approved()->count());
        $this->assertSame(1, ProductReview::query()->pending()->count());
    }

    #[Test]
    public function notification_scopeUnread_filters_correctly(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        $user = \App\Models\Auth\User::factory()->create();

        Notification::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'title' => 'A',
            'message' => 'msg',
            'type' => 'info',
            'is_read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'title' => 'B',
            'message' => 'msg',
            'type' => 'info',
            'is_read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'title' => 'C',
            'message' => 'msg',
            'type' => 'info',
            'is_read' => true,
        ]);

        $this->assertSame(2, Notification::query()->unread()->count());
    }

    #[Test]
    public function store_hasFeature_works_for_active_features(): void
    {
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create();
        StoreDomain::query()->create([
            'store_id' => $store->id,
            'domain' => 'x.test',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        StoreFeature::query()->create([
            'store_id' => $store->id,
            'slug' => 'wishlist',
            'is_active' => true,
        ]);
        StoreFeature::query()->create([
            'store_id' => $store->id,
            'slug' => 'reviews',
            'is_active' => false,
        ]);

        $this->assertTrue($store->hasFeature('wishlist'));
        $this->assertFalse($store->hasFeature('reviews'));
        $this->assertFalse($store->hasFeature('nonexistent'));
    }

    #[Test]
    public function boolean_where_generates_is_not_null_sql_not_is_null(): void
    {
        // Sanity check: el SQL generado NO debe contener `is_active is null`.
        $sql = Promotion::query()->valid()->toRawSql();
        $this->assertStringContainsString('is_active', $sql);
        $this->assertStringNotContainsString('is_active" is null', $sql);

        $sql2 = Coupon::query()->valid()->toRawSql();
        $this->assertStringNotContainsString('is_active" is null', $sql2);
    }
}
