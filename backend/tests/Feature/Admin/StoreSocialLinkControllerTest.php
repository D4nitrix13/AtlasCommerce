<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Stores\StoreSocialLink;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD admin de redes sociales (RF-STOR-133).
 *
 * Cubre:
 *  - Auth: 401 sin token, 403 sin `stores.manage`.
 *  - Listado paginado.
 *  - Store con validación platform enum + url.
 *  - Update parcial.
 *  - Destroy.
 *  - Cross-store: 404 al intentar editar link de otra tienda.
 */
#[Group('admin')]
#[Group('stores')]
final class StoreSocialLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create(['slug' => 'kitsune']);
        StoreDomain::query()->create([
            'store_id' => $this->storeB->id,
            'domain' => 'kitsune.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        $this->unauthorized = User::factory()->create();
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links");
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_stores_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links");
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function it_stores_social_link(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links",
            data: [
                'platform' => 'instagram',
                'url' => 'https://instagram.com/pandaestampados',
                'display_name' => '@pandaestampados',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.platform', expect: 'instagram');
        $response->assertJsonPath(path: 'data.display_name', expect: '@pandaestampados');
    }

    #[TestAttr]
    public function it_validates_platform_enum(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links",
            data: [
                'platform' => 'myspace', // not in enum
                'url' => 'https://example.com',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['platform']);
    }

    #[TestAttr]
    public function it_validates_url_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links",
            data: [
                'platform' => 'instagram',
                'url' => 'not-a-url',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['url']);
    }

    #[TestAttr]
    public function it_updates_partial_fields(): void
    {
        $link = StoreSocialLink::query()->create([
            'store_id' => $this->storeA->id,
            'platform' => 'instagram',
            'url' => 'https://instagram.com/old',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links/{$link->id}",
            data: [
                'url' => 'https://instagram.com/new',
                'sort_order' => 5,
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.url', expect: 'https://instagram.com/new');
        $response->assertJsonPath(path: 'data.sort_order', expect: 5);
        $response->assertJsonPath(path: 'data.platform', expect: 'instagram'); // inmutable
    }

    #[TestAttr]
    public function it_destroys_link(): void
    {
        $link = StoreSocialLink::query()->create([
            'store_id' => $this->storeA->id,
            'platform' => 'tiktok',
            'url' => 'https://tiktok.com/@panda',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links/{$link->id}",
        )->assertNoContent();

        $this->assertDatabaseMissing(table: 'store_social_links', data: ['id' => $link->id]);
    }

    #[TestAttr]
    public function cross_store_link_returns_404(): void
    {
        $linkInB = StoreSocialLink::query()->create([
            'store_id' => $this->storeB->id,
            'platform' => 'facebook',
            'url' => 'https://facebook.com/kitsune',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        // Admin de storeA intenta editar link de storeB → 404.
        $this->putJson(
            uri: "http://panda-estampados.com/api/admin/stores/{$this->storeA->id}/social-links/{$linkInB->id}",
            data: ['url' => 'https://hack.com'],
        )->assertStatus(status: 404);
    }
}
