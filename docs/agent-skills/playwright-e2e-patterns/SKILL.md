<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: playwright-e2e-patterns
description: E2E tests with Playwright. tests/e2e/ structure, baseURL, commands, auth flow patterns, multi-tenant. Triggers: creating/modifying tests/e2e/, playwright debugging, adding test domain.
---

# Playwright E2E Patterns

Source: `docs/development/testing.md` (Playwright section) + `frontend/playwright.config.ts`.

## Prerequisites

```bash
cd frontend
npx playwright install
```

## Commands

```bash
# Full suite
npx playwright test

# Specific test
npx playwright test tests/e2e/login.spec.ts

# With UI
npx playwright test --ui

# Debug mode
npx playwright test --debug

# HTML report
npx playwright show-report
```

## Configuration

`frontend/playwright.config.ts`:

```ts
{
 testDir: "./tests/e2e",
 fullyParallel: true,
 forbidOnly: !!process.env.CI,
 retries: process.env.CI ? 2 : 0,
 workers: process.env.CI ? 2 : undefined,
 reporter: process.env.CI ? "github" : "list",
 use: {
 baseURL: "http://panda-estampados.com",
 trace: "retain-on-failure",
 screenshot: "only-on-failure",
 video: "retain-on-failure",
 },
}
```

**Pre-requisites for tests to run:**

- Postgres + Redis (docker compose up in `docker/dev`)
- Backend Laravel (`php artisan serve --host=127.0.0.1 --port=8000`)
- Frontend Astro (`pnpm run dev`)
- Caddy on port 80 with `/etc/hosts` configured

If any is missing, tests fail with a connection error.

## Test structure

```txt
frontend/tests/e2e/
├── auth/
│ ├── login.spec.ts
│ └── register.spec.ts
├── storefront/
│ └── checkout.spec.ts
└── admin/
 └── dashboard.spec.ts
```

## Convention

- **One file per flow**.
- Filename = same as the flow (`login.spec.ts` for login).
- Tests inside with optional `describe()` and direct `test()`.

## Example: admin login

```ts
import { test, expect } from "@playwright/test";

test("admin login in Admin Panda", async ({ page }) => {
 await page.goto("http://admin.panda-estampados.com/login");

 await expect(page).toHaveTitle(/Panda Estampados/);

 await page.getByLabel("Email").fill("admin@panda-estampados.test");
 await page.getByLabel("Password").fill("password");
 await page.getByRole("button", { name: "Iniciar sesión" }).click();

 await expect(page).toHaveURL(/dashboard/);
});
```

## Multi-tenant

Base URLs in this project:

| Domain | For |
|---|---|
| `http://panda-estampados.com` | Panda storefront |
| `http://admin.panda-estampados.com` | Panda admin |
| `http://kitsune.com` | Kitsune storefront |
| `http://admin.kitsune.com` | Kitsune admin |

`/etc/hosts` must have:

```txt
127.0.0.1 panda-estampados.com
127.0.0.1 admin.panda-estampados.com
127.0.0.1 kitsune.com
127.0.0.1 admin.kitsune.com
```

## Recommended selectors

Preference order:

1. **Role + accessible name** - `page.getByRole("button", { name: "Submit" })`
2. **Label** - `page.getByLabel("Email")`
3. **Placeholder** - `page.getByPlaceholder("tu@correo.com")`
4. **Text** - `page.getByText("Iniciar sesión")`
5. **Test ID** - `page.getByTestId("submit")` (add `data-testid` in the component)
6. **CSS** - `page.locator(".btn-primary")` (last resort)

## What NOT to use

- ❌ `page.locator(".css-class-from-tailwind")` - fragile.
- ❌ XPath - hard to maintain.
- ❌ Auto-generated IDs that change.

## Frequent patterns

### Login and logout

```ts
test.beforeEach(async ({ page }) => {
 // Login before each test
 await page.goto("/login");
 await page.getByLabel("Email").fill(...);
 await page.getByLabel("Password").fill(...);
 await page.getByRole("button", { name: "Login" }).click();
 await expect(page).toHaveURL(/dashboard/);
});
```

### Reusable auth state

```ts
// playwright.config.ts
use: {
 storageState: "playwright/.auth/user.json",
},
```

```ts
// Setup project
// auth.setup.ts
await page.goto("/login");
// ... login
await page.context().storageState({ path: "playwright/.auth/user.json" });
```

### API mocking

```ts
test("test with mocked API", async ({ page }) => {
 await page.route("/api/products", async (route) => {
 await route.fulfill({
 status: 200,
 body: JSON.stringify([{ id: 1, name: "Mocked" }]),
 });
 });

 await page.goto("/products");
 await expect(page.getByText("Mocked")).toBeVisible();
});
```

### Visual regression (optional)

```ts
await expect(page).toHaveScreenshot("login.png", {
 maxDiffPixels: 100,
});
```

## Debugging

```bash
# UI mode
npx playwright test --ui

# Debug mode (pause + DevTools)
npx playwright test --debug

# Trace viewer (after a failure)
npx playwright show-trace test-results/.../trace.zip
```

## Trigger patterns

Activate when:

- Creating a new E2E test in `tests/e2e/`.
- Debugging a failing test.
- Adding a new test domain.
- Configuring reusable auth state.
- Changes that break existing flows.

## Anti-patterns

- ❌ Hardcoded long timeouts (`await page.waitForTimeout(5000)`).
- ❌ Selectors by index (`locator("button").nth(3)`).
- ❌ Tests that depend on each other.
- ❌ Snapshots that don't fail (no assertion).
- ❌ Tests that ignore console errors.
- ❌ Tests that assume shared state.

## AAA pattern

Recommended: Arrange, Act, Assert.

```ts
test("login", async ({ page }) => {
 // Arrange
 await page.goto("/login");

 // Act
 await page.getByLabel("Email").fill("admin@example.com");
 await page.getByLabel("Password").fill("password");
 await page.getByRole("button", { name: "Login" }).click();

 // Assert
 await expect(page).toHaveURL(/dashboard/);
});
```

## CI

`.github/workflows/quality-gate.yml` runs E2E tests in separate jobs
(frontend). Needs:

- Postgres + Redis running.
- Backend build.
- Frontend build.
- Caddy configured.

## Related skills

- `quality-gate-pre-commit` - runs tests before commit.
- `astro-frontend-structure` - selectors should follow UI conventions.
- `laravel-multi-tenant-patterns` - understand domains for tests.
- `conventional-commits-and-prs` - commit messages for tests.
