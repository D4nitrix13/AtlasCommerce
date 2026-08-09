# Testing — Backend y Frontend

Estrategias de testing y comandos para correr las suites.

## Backend — PHPUnit

`PHPUnit` 12 con `RefreshDatabase` trait. 142 tests al día de hoy.

### Comandos

```bash
cd backend

# Toda la suite
composer test

# Test específico
php artisan test --compact --filter=login_with_valid_credentials

# Por grupo
php artisan test --group=auth
php artisan test --group=http
php artisan test --group=multi-tenant

# Por archivo
php artisan test tests/Feature/Auth/AuthHttpTest.php

# Solo los del directorio
php artisan test tests/Feature/Auth/

# Detener en primer fallo
php artisan test --stop-on-failure

# Con coverage
php artisan test --coverage
```

### Estructura

```txt
tests/
├── Feature/                    # Tests HTTP / integración
│   ├── Auth/
│   │   ├── AuthHttpTest.php
│   │   └── CheckPermissionMiddlewareTest.php
│   ├── Http/
│   │   ├── CartApiTest.php
│   │   ├── CheckoutApiTest.php
│   │   ├── MultiTenantHttpIsolationTest.php
│   │   ├── PaymentApiTest.php
│   │   └── Returns/
│   ├── Inventory/
│   ├── Returns/
│   ├── Shipping/
│   └── Stores/
├── Unit/                       # Tests unitarios
└── TestCase.php                # Base class
```

### Convenciones

Nombrar tests con el comportamiento que verifican:

```php
public function login_with_valid_credentials_returns_token_and_user(): void
public function login_from_host_without_domain_returns_404(): void
public function store_domain_middleware_filters_query_by_store_id(): void
```

### Atributos

```php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('auth')]
#[Group('http')]
final class AuthHttpTest extends TestCase
{
    #[Test]
    public function login_with_valid_credentials_returns_token_and_user(): void
    {
        // ...
    }
}
```

### Tests de multi-tenancy

Ver [architecture/multi-store.md](../../architecture/multi-store.md "architecture/multi-store.md")
y `MultiTenantHttpIsolationTest.php` para patrón.

Puntos clave:

- Usar `RefreshDatabase` para migrar limpio.
- Setear `app(StoreContextService::class)->setStore($store)` antes de los requests.
- Verificar que queries con `store_id` filtran correctamente.

### Tests con HTTP

```php
public function example_test(): void
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/users');
    $response->assertOk();
    $response->assertJsonPath('data.0.email', $user->email);
}
```

## Frontend — Playwright

Tests E2E con `Playwright` para verificar flujos completos.

### Instalación

```bash
cd frontend
npx playwright install
```

### Comandos

```bash
# Correr todos los tests
npx playwright test

# Test específico
npx playwright test tests/e2e/login.spec.ts

# Con UI
npx playwright test --ui

# Modo debug
npx playwright test --debug

# Generar reporte HTML
npx playwright show-report
```

### Estructura

```txt
tests/
└── e2e/
    ├── auth/
    │   ├── login.spec.ts
    │   └── register.spec.ts
    ├── storefront/
    │   └── checkout.spec.ts
    └── admin/
        └── dashboard.spec.ts
```

### Ejemplo

```ts
import { test, expect } from "@playwright/test";

test("admin login en Admin Panda", async ({ page }) => {
  await page.goto("http://admin.panda-estampados.com/login");

  await expect(page).toHaveTitle(/Panda Estampados/);

  await page.getByLabel("Email").fill("admin@panda-estampados.test");
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Iniciar sesión" }).click();

  await expect(page).toHaveURL(/dashboard/);
});
```

## Convenciones compartidas

- **AAA** (Arrange, Act, Assert).
- **Una aserción conceptual por test** (puede ser varias líneas).
- **Nombres descriptivos** que expliquen el comportamiento.
- **Test del happy path** + edge cases + errores esperados.
- **No testear implementación**, testear comportamiento.

## CI

Pipeline de GitHub Actions en
`.github/workflows/quality-gate.yml`:

- Job backend: Pint + PHPStan + PHPUnit.
- Job frontend: `astro check` + Prettier.
- Job docs: markdownlint.

Ver [linting.md](linting.md "linting.md").

## Próximo archivo

[linting.md](linting.md "linting.md") — Linters y formatters.
