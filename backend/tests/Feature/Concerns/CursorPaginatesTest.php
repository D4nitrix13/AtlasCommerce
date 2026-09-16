<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Http\Controllers\Api\Test\CursorPaginatesTestController;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\System\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del trait `CursorPaginates` (RF-PERF-022).
 *
 * Cubre:
 *  - perPage se clampea a MAX_PER_PAGE y mínimo 1.
 *  - response shape estable: { data, meta: { next_cursor, prev_cursor, has_more, per_page } }.
 *  - cursor del query string se pasa correctamente.
 *  - múltiples páginas se navegan con el cursor devuelto.
 *  - cursor inválido no rompe (Laravel ignora y retorna primera página).
 */
#[Group('pagination')]
final class CursorPaginatesTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private string $host;

    private CursorPaginatesTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
        $this->host = "{$this->store->slug}.test";
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->controller = new CursorPaginatesTestController();
    }

    private function makeNotifications(int $count): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        for ($i = 0; $i < $count; $i++) {
            Notification::query()->create([
                'user_id' => $admin->id,
                'store_id' => $this->store->id,
                'title' => 'Test ' . $i,
                'message' => 'Body',
                'type' => 'info',
                'is_read' => false,
            ]);
        }
    }

    private function gitNotification(): \Illuminate\Http\Request
    {
        return \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications", 'GET');
    }

    #[Test]
    public function response_shape_is_stable(): void
    {
        $this->makeNotifications(3);

        $response = $this->controller->index($this->gitNotification());
        // assertOk no aplica a JsonResponse directo (TestResponse sí)

        $data = $response->getData(true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('next_cursor', $data['meta']);
        $this->assertArrayHasKey('prev_cursor', $data['meta']);
        $this->assertArrayHasKey('has_more', $data['meta']);
        $this->assertArrayHasKey('per_page', $data['meta']);
    }

    #[Test]
    public function per_page_defaults_to_20(): void
    {
        $this->makeNotifications(3);

        $response = $this->controller->index($this->gitNotification());
        $this->assertSame(20, $response->getData(true)['meta']['per_page']);
    }

    #[Test]
    public function per_page_clamped_to_max(): void
    {
        $this->makeNotifications(3);

        $request = \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=99999", 'GET');
        $response = $this->controller->index($request);
        $this->assertSame(100, $response->getData(true)['meta']['per_page']);
    }

    #[Test]
    public function per_page_min_is_1(): void
    {
        $this->makeNotifications(3);

        $request = \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=0", 'GET');
        $response = $this->controller->index($request);
        $this->assertSame(1, $response->getData(true)['meta']['per_page']);
    }

    #[Test]
    public function returns_has_more_true_when_more_pages(): void
    {
        $this->makeNotifications(25);

        $request = \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=10", 'GET');
        $response = $this->controller->index($request);
        $data = $response->getData(true);
        $this->assertTrue($data['meta']['has_more']);
        $this->assertCount(10, $data['data']);
        $this->assertNotNull($data['meta']['next_cursor']);
    }

    #[Test]
    public function returns_has_more_false_on_last_page(): void
    {
        $this->makeNotifications(5);

        $request = \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=10", 'GET');
        $response = $this->controller->index($request);
        $data = $response->getData(true);
        $this->assertFalse($data['meta']['has_more']);
        $this->assertCount(5, $data['data']);
        $this->assertNull($data['meta']['next_cursor']);
    }

    #[Test]
    public function cursor_paginates_through_all_pages(): void
    {
        $this->makeNotifications(25);

        $first = $this->controller->index(
            \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=10", 'GET'),
        );
        $firstData = $first->getData(true);
        $this->assertCount(10, $firstData['data']);
        $this->assertNotNull($firstData['meta']['next_cursor']);

        $cursor = $firstData['meta']['next_cursor'];
        $second = $this->controller->index(
            \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=10&cursor={$cursor}", 'GET'),
        );
        $secondData = $second->getData(true);
        $this->assertCount(10, $secondData['data']);
        $this->assertNotNull($secondData['meta']['next_cursor']);

        $cursor = $secondData['meta']['next_cursor'];
        $third = $this->controller->index(
            \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?per_page=10&cursor={$cursor}", 'GET'),
        );
        $thirdData = $third->getData(true);
        $this->assertCount(5, $thirdData['data']);
        $this->assertFalse($thirdData['meta']['has_more']);

        $allIds = array_merge(
            array_column($firstData['data'], 'id'),
            array_column($secondData['data'], 'id'),
            array_column($thirdData['data'], 'id'),
        );
        $this->assertCount(25, $allIds);
        $this->assertSame(25, count(array_unique($allIds)));
    }

    #[Test]
    public function invalid_cursor_returns_first_page(): void
    {
        $this->makeNotifications(5);

        $request = \Illuminate\Http\Request::create("http://{$this->host}/api/v1/notifications?cursor=invalid-garbage", 'GET');
        $response = $this->controller->index($request);

        // assertOk no aplica a JsonResponse directo (TestResponse sí)
        $this->assertCount(5, $response->getData(true)['data']);
    }
}
