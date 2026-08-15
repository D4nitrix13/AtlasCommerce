---
name: laravel-feature-testing
description: PHPUnit feature/integration tests for Laravel 13 + PHP 8.5. RefreshDatabase vs DatabaseTransactions, factory states, multi-tenant isolation, Sanctum, MFA, named args. Triggers: writing/editing tests in backend/tests/, debugging test failures, attaching an agent to write tests.
---

# Laravel Feature Testing

Source: `docs/development/testing.md` (PHPUnit section) + `backend/AGENTS.md`.

## Stack

- **PHPUnit 12** (no Pest - convert if it appears).
- **Test runner**: `php artisan test --compact` (filter by filename or `--filter=Name`).
- **Test dirs**: `backend/tests/Feature/` (integration), `backend/tests/Unit/` (pure).
- **Mocks**: Mockery (NO hand-rolled doubles).

## Conventions

- **Naming**: `it_does_x_when_y` or `testDoesXWhenY` (snake_case preferred, see `backend/AGENTS.md`).
- **Always isolated**: each test runs alone, does not depend on order.
- **One logical assertion per test** when possible.
- **Factories** (`database/factories/*`) - use before manual setUp. Check if the factory has custom states.
- **Multi-tenant tests**: see `tests/Feature/Http/MultiTenantHttpIsolationTest.php` as template. Pattern: 2 stores, request to domain A, assert scoped visibility.
- **Sanctum tests**: `Sanctum::actingAs($user, ['*'], 'web')` to authenticate, `$this->getJson(...)` for endpoint.
- **MFA tests**: `RecoveryCodeFlowTest`, `TwoFactorLoginFlowTest` - when adding a new 2FA method, follow these as template.

## RefreshDatabase vs DatabaseTransactions

| Trait | When to use | Speed |
|---|---|---|
| `RefreshDatabase` | Tests that modify schema (migrations) or want full rollback | Slow (runs migrations + rollback) |
| `DatabaseTransactions` | Tests that only insert/read data without touching schema | Fast (rollback per test) |

**Default in this project**: `RefreshDatabase` (safer).

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AuthHttpTest extends TestCase
{
 use RefreshDatabase;
 // ...
}
```

> ⚠️ If you add a migration **during** the test, you need `RefreshDatabase`
> (transactions don't apply to DDL).

## Factories

### Location

`backend/database/factories/{Modulo}/{Model}Factory.php`.

### Naming

```php
class UserFactory extends Factory
{
 protected $model = User::class;
 
 public function definition(): array
 {
 return [
 'name' => fake()->name(),
 'email' => fake()->unique()->safeEmail(),
 // ...
 ];
 }
}
```

Usage:

```php
$user = User::factory()->create();

// With attribute overrides
$user = User::factory()->create([
 'email' => 'specific@example.com',
]);

// Without persisting (in memory only)
$user = User::factory()->make();
```

### Factory States

For reusable variants of the same model:

```php
public function admin(): static
{
 return $this->state(fn (array $attrs) => [
 'is_admin' => true,
 ]);
}

public function unverified(): static
{
 return $this->state(fn (array $attrs) => [
 'email_verified_at' => null,
 ]);
}
```

Usage:

```php
$admin = User::factory()->admin()->create();
$newUser = User::factory()->unverified()->create();
$pendingAdmin = User::factory()->admin()->unverified()->create();
```

### Factory relationships

```php
public function withOrders(int $count = 3): static
{
 return $this->has(Order::factory()->count($count))->create();
}

// Usage
$user = User::factory()->withOrders(5)->create();
```

> ⚠️ Check if the factory already has states before creating new ones.

## Named args (CRITICAL)

See `backend/AGENTS.md` rule #1 - **always** use named args in:

- Eloquent: `->where(column: 'x', operator: '=', value: 1)`, `firstOrCreate(attributes: [...], values: [...])`, `findOrFail(id: 1)`.
- `Hash::check(value: ..., hashedValue: ...)`.
- `Validator::make(data: ..., rules: ..., messages: ...)`.
- PHPUnit multi-arg: `assertSame(expected: 0, actual: $count)`.

**DO NOT** use named args for:

- Single-arg calls.
- Associative arrays (keys are the doc).
- Fluents where only closure is passed.

## Assertions

### HTTP

```php
$response = $this->getJson('/api/users');
$response->assertOk(); // 200
$response->assertForbidden(); // 403
$response->assertUnprocessable(); // 422
$response->assertNotFound(); // 404
$response->assertJsonPath('data.0.email', $user->email);
$response->assertJsonStructure(['data' => ['id', 'email']]);
```

### DB

```php
$this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
$this->assertDatabaseMissing('users', ['email' => 'deleted@example.com']);
$this->assertDatabaseCount('users', 3);
$this->assertSoftDeleted($user);
```

### Auth

```php
$this->assertAuthenticated();
$this->assertAuthenticatedAs($user);
$this->assertGuest();
```

### Validation

```php
$response->assertJsonValidationErrors(['email', 'password']);
$response->assertJsonValidationErrorFor('email');
```

## PHPUnit attributes

```php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('auth')]
#[Group('http')]
final class AuthHttpTest extends TestCase
{
 #[Test]
 public function user_can_login_with_valid_credentials(): void
 {
 // ...
 }
}
```

## Test groups

```bash
# By group
php artisan test --group=auth
php artisan test --group=http
php artisan test --group=multi-tenant
```

## Per-feature test patterns

| Feature | Test file | Pattern |
|---|---|---|
| Auth flow | `tests/Feature/Auth/AuthHttpTest.php` | Login + token + refresh |
| Password reset | `tests/Feature/Auth/PasswordResetFlowTest.php` | Forgot → email → reset |
| 2FA | `tests/Feature/Auth/TwoFactorLoginFlowTest.php` | TOTP challenge |
| Recovery codes | `tests/Feature/Auth/RecoveryCodeFlowTest.php` | Recovery code login |
| Social login | `tests/Feature/Auth/SocialAuthControllerTest.php` | OAuth redirect |
| Multi-tenant | `tests/Feature/Http/MultiTenantHttpIsolationTest.php` | Domain isolation |
| Cart | `tests/Feature/Http/CartApiTest.php` | Add → update → remove |
| Checkout | `tests/Feature/Http/CheckoutApiTest.php` | Happy + failure paths |
| Payment | `tests/Feature/Http/PaymentApiTest.php` | Stripe flow |
| Bootstrap | `tests/Feature/Console/BootstrapTenantCommandTest.php` | Tenant seeder |

## Multi-tenant test pattern

```php
public function store_domain_middleware_filters_query_by_store_id(): void
{
 // Arrange: 2 stores
 $storeA = Store::factory()->create(['slug' => 'panda']);
 $storeB = Store::factory()->create(['slug' => 'kitsune']);

 // Create data in each store
 $productA = Product::factory()->create(['store_id' => $storeA->id]);
 $productB = Product::factory()->create(['store_id' => $storeB->id]);

 // Set store context
 app(StoreContextService::class)->setStore($storeA);

 // Act: request to domain A
 $response = $this->getJson('http://panda-estampados.com/api/products');

 // Assert: only data from A
 $response->assertOk();
 $response->assertJsonCount(1, 'data');
 $response->assertJsonPath('data.0.id', $productA->id);
}
```

Key points:

- Set `app(StoreContextService::class)->setStore($store)` before requests.
- Verify queries with `store_id` filter correctly.
- Test with different domains to ensure the middleware works.

## Running

```bash
# Single test
php artisan test --compact --filter=test_user_can_login

# Single file
php artisan test --compact tests/Feature/Auth/AuthHttpTest.php

# All tests
php artisan test --compact

# Stop on first failure
php artisan test --stop-on-failure

# With coverage
php artisan test --coverage
php artisan test --coverage-html=storage/coverage
```

**Pre-commit hook**: `composer precommit` (lint:dirty + analyse + test).

## Anti-patterns

- ❌ Tests that depend on execution order.
- ❌ `DB::statement('DELETE FROM ...')` inside a test - use `RefreshDatabase`.
- ❌ Inline factories in tests when one exists in `database/factories/`.
- ❌ Skipped tests without documented reason in `markTestSkipped()`.
- ❌ Hardcoded `store_id` in multi-tenant tests - use the template helper.
- ❌ `assertDatabaseCount` when you only need 1 - use `assertDatabaseHas`.
- ❌ `assertEquals` (deprecated) - use `assertSame`.
- ❌ Mocking the whole model - use factory + real persistence.
- ❌ Testing implementation (method names), test behavior.

## File map

```
backend/tests/Feature/ ← integration tests
backend/tests/Unit/ ← pure unit tests
backend/tests/Feature/Auth/ ← auth vertical
backend/tests/Feature/Http/ ← API endpoints
backend/tests/Feature/Console/ ← artisan commands
backend/database/factories/ ← factories
backend/phpunit.xml ← config
backend/composer.json ← "test" script + "precommit"
```

## Related skills

- `laravel-multi-tenant-patterns` - domain isolation in tests.
- `laravel-architecture-decisions` - when Service vs Controller in tests.
- `redis-caching-patterns` - when tests cache cross-tenant things.
- `quality-gate-pre-commit` - runs `composer precommit` before commit.
