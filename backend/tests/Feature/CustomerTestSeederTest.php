<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use Database\Seeders\CustomerTestSeeder;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('seeders')]
#[Group('customer-auth')]
final class CustomerTestSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
    }

    #[Test]
    public function creates_two_customers_with_orders(): void
    {
        $this->seed(class: CustomerTestSeeder::class);

        $this->assertSame(
            expected: 2,
            actual: Customer::query()->count(),
            message: 'Expected 2 customers (Panda + Kitsune)',
        );

        $this->assertSame(
            expected: 5,
            actual: Order::query()->count(),
            message: 'Expected 5 orders total (3 Panda + 2 Kitsune)',
        );
    }

    #[Test]
    public function customers_have_role_customer(): void
    {
        $this->seed(class: CustomerTestSeeder::class);

        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();

        $userCount = User::query()->where('role_id', $customerRole->id)->count();
        $this->assertSame(
            expected: 2,
            actual: $userCount,
            message: 'Both seeded users should have role=customer',
        );
    }

    #[Test]
    public function customers_are_linked_to_their_stores(): void
    {
        $this->seed(class: CustomerTestSeeder::class);

        $pandaCustomer = Customer::query()->where('email', 'cliente@panda-estampados.test')->firstOrFail();
        $kitsuneCustomer = Customer::query()->where('email', 'cliente@kitsune.test')->firstOrFail();

        $this->assertSame(
            expected: 'panda-estampados',
            actual: $pandaCustomer->stores()->first()->slug,
        );
        $this->assertSame(
            expected: 'kitsune',
            actual: $kitsuneCustomer->stores()->first()->slug,
        );
    }

    #[Test]
    public function is_idempotent(): void
    {
        $this->seed(class: CustomerTestSeeder::class);
        $this->seed(class: CustomerTestSeeder::class);

        $this->assertSame(
            expected: 2,
            actual: Customer::query()->count(),
            message: 'Second run should not duplicate customers',
        );
    }
}
