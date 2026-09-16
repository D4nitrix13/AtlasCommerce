<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `carts:purge-abandoned` (RF-STOR-132).
 *
 * Cubre:
 *  - Purga carts `active` con `expires_at` pasado.
 *  - Purga carts `active` sin `expires_at` pero con `updated_at` viejo.
 *  - NO purga carts `converted` aunque estén viejos.
 *  - NO purga carts `active` recientes.
 *  - `--dry-run` cuenta sin eliminar.
 *  - `--days` corta correctamente.
 */
#[Group('maintenance')]
#[Group('cart')]
final class PurgeAbandonedCartsTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function purges_active_carts_with_expired_expires_at(): void
    {
        $expired = $this->makeCart([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);
        $active = $this->makeCart([
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('carts:purge-abandoned', ['--days' => 30])
            ->assertExitCode(0);

        $this->assertNull(Cart::find($expired->id), 'cart con expires_at pasado debe purgarse');
        $this->assertNotNull(Cart::find($active->id), 'cart vigente NO debe purgarse');
    }

    #[Test]
    public function purges_active_carts_without_expires_at_when_stale(): void
    {
        $stale = $this->makeCart([
            'status' => 'active',
            'expires_at' => null,
            'updated_at' => now()->subDays(60),
        ]);
        $fresh = $this->makeCart([
            'status' => 'active',
            'expires_at' => null,
            'updated_at' => now()->subDays(5),
        ]);

        $this->artisan('carts:purge-abandoned', ['--days' => 30])
            ->expectsOutputToContain('Purgados 1 carritos')
            ->assertExitCode(0);

        $this->assertNull(Cart::find($stale->id));
        $this->assertNotNull(Cart::find($fresh->id));
    }

    #[Test]
    public function does_not_purge_converted_carts(): void
    {
        $converted = $this->makeCart([
            'status' => 'converted',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('carts:purge-abandoned', ['--days' => 30])
            ->expectsOutputToContain('Purgados 0 carritos')
            ->assertExitCode(0);

        $this->assertNotNull(Cart::find($converted->id));
    }

    #[Test]
    public function dry_run_does_not_delete(): void
    {
        $cart = $this->makeCart([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('carts:purge-abandoned', ['--days' => 30, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertNotNull(Cart::find($cart->id), 'dry-run debe preservar el cart');
    }

    #[Test]
    public function days_parameter_shortens_window(): void
    {
        $cart = $this->makeCart([
            'status' => 'active',
            'expires_at' => null,
            'updated_at' => now()->subDays(10),
        ]);

        // days=30: cart de 10 días NO se purga.
        $this->artisan('carts:purge-abandoned', ['--days' => 30])
            ->expectsOutputToContain('Purgados 0 carritos')
            ->assertExitCode(0);
        $this->assertNotNull(Cart::find($cart->id));

        // days=5: cart de 10 días SÍ se purga.
        $this->artisan('carts:purge-abandoned', ['--days' => 5])
            ->expectsOutputToContain('Purgados 1 carritos')
            ->assertExitCode(0);
        $this->assertNull(Cart::find($cart->id));
    }

    #[Test]
    public function invalid_days_returns_error(): void
    {
        $this->artisan('carts:purge-abandoned', ['--days' => 0])
            ->expectsOutputToContain('debe ser >= 1')
            ->assertExitCode(2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCart(array $overrides): Cart
    {
        return Cart::factory()->create(array_merge([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
        ], $overrides));
    }
}
