<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Services\Customers\CustomerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre los endpoints GDPR de cuenta del cliente.
 *
 * RF-CUST-042 — Exportar y borrar datos del cliente.
 *
 * Los endpoints viven en /api/account/* y requieren autenticación
 * Sanctum (User con Customer vinculado).
 */
#[Group('customers')]
#[Group('gdpr')]
final class AccountDataControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(attributes: [
            'email' => 'gdpr-customer@example.com',
            'password' => 'my-strong-password',
            'is_active' => true,
        ]);

        // Vincular User ↔ Customer (1:1 opcional).
        $this->customer = Customer::factory()->create(attributes: [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function export_requires_authentication(): void
    {
        $response = $this->getJson(uri: '/api/account/data-export');

        $response->assertStatus(status: 401);
    }

    #[Test]
    public function export_returns_full_payload_for_authenticated_customer(): void
    {
        // Seed mínimo: una dirección para verificar que aparece en
        // la sección correspondiente del export.
        CustomerAddress::factory()->create(attributes: [
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: '/api/account/data-export');

        $response->assertOk();
        $response->assertJsonStructure(structure: [
            'metadata' => ['exported_at', 'schema_version', 'customer_id', 'anonymized'],
            'profile' => ['customer' => ['id', 'email', 'first_name', 'last_name'], 'user'],
            'addresses',
            'orders',
            'invoices',
            'payments',
            'refunds',
            'return_requests',
            'carts',
            'wishlists',
            'product_reviews',
            'product_questions',
            'support_tickets',
            'custom_designs',
            'coupon_usages',
        ]);

        // La metadata debe apuntar al customer autenticado.
        $response->assertJsonPath(path: 'metadata.customer_id', expect: $this->customer->id);
        $response->assertJsonPath(path: 'profile.customer.email', expect: 'gdpr-customer@example.com');
    }

    #[Test]
    public function export_returns_404_when_user_has_no_customer_linked(): void
    {
        $adminOnly = User::factory()->create(attributes: [
            'email' => 'admin-no-customer@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $adminOnly, abilities: ['*']);

        $response = $this->getJson(uri: '/api/account/data-export');

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function deletion_request_generates_active_token(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: '/api/account/deletion-request');

        $response->assertStatus(status: 202);
        $response->assertJsonStructure(structure: ['message', 'token', 'expires_at']);

        $token = (string) $response->json(key: 'token');
        $this->assertGreaterThanOrEqual(32, strlen($token));

        // El customer debe tener un hash guardado + expiración.
        $fresh = $this->customer->fresh();
        $this->assertNotNull($fresh?->deletion_token_hash);
        $this->assertNotNull($fresh?->deletion_token_expires_at);
        $this->assertNotNull($fresh?->deletion_requested_at);
        $this->assertTrue($fresh->hasPendingDeletionToken());
    }

    #[Test]
    public function deletion_confirm_requires_token_and_password(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/account', data: []);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['token', 'password']);
    }

    #[Test]
    public function deletion_confirm_rejects_invalid_token(): void
    {
        $service = app(abstract: CustomerDeletionService::class);
        $service->requestDeletion(customer: $this->customer);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/account', data: [
            'token' => str_repeat(string: 'a', times: 64),
            'password' => 'my-strong-password',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El token de borrado no es válido.');
    }

    #[Test]
    public function deletion_confirm_rejects_wrong_password(): void
    {
        $service = app(abstract: CustomerDeletionService::class);
        $token = $service->requestDeletion(customer: $this->customer);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/account', data: [
            'token' => $token,
            'password' => 'WRONG-PASSWORD',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'La contraseña no es correcta.');
    }

    #[Test]
    public function deletion_confirm_anonymizes_pii_and_soft_deletes(): void
    {
        $service = app(abstract: CustomerDeletionService::class);
        $token = $service->requestDeletion(customer: $this->customer);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: '/api/account', data: [
            'token' => $token,
            'password' => 'my-strong-password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(structure: ['message', 'anonymized_at']);

        // Recargar desde BD ignorando soft-delete para verificar.
        $customerRaw = Customer::withTrashed()->find($this->customer->id);
        $this->assertNotNull($customerRaw);
        $this->assertSame('Deleted', $customerRaw->first_name);
        $this->assertSame('User', $customerRaw->last_name);
        $this->assertStringStartsWith('deleted-', $customerRaw->email);
        $this->assertStringEndsWith('@anonymized.invalid', $customerRaw->email);
        $this->assertNull($customerRaw->phone);
        $this->assertNull($customerRaw->identity_document);
        $this->assertFalse($customerRaw->is_active);
        $this->assertNotNull($customerRaw->anonymized_at);
        $this->assertNotNull($customerRaw->deleted_at);

        // El User vinculado también queda anonimizado.
        $userFresh = $this->user->fresh();
        $this->assertSame('Deleted', $userFresh->first_name);
        $this->assertSame('User', $userFresh->last_name);
        $this->assertStringStartsWith('deleted-', $userFresh->email);
    }

    #[Test]
    public function deletion_cancel_clears_pending_token(): void
    {
        $service = app(abstract: CustomerDeletionService::class);
        $service->requestDeletion(customer: $this->customer);
        $this->assertTrue($this->customer->fresh()->hasPendingDeletionToken());

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: '/api/account/deletion-cancel');

        $response->assertOk();
        $this->assertFalse($this->customer->fresh()->hasPendingDeletionToken());
    }
}
