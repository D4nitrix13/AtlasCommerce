import { test, expect } from '@playwright/test';

/**
 * E2E tests para el flujo de autenticación admin con el nuevo design
 * system (Storyset Amico).
 *
 * El admin NO tiene registro público. Solo login + forgot-password.
 *
 * Pre-requisito: backend, frontend y Caddy corriendo localmente.
 * Las URLs usan admin.panda-estampados.com (Panda Estampados)
 * mapeado en /etc/hosts.
 */

const ADMIN_PANDA = 'http://admin.panda-estampados.com';
const ADMIN_KITSUNE = 'http://admin.kitsune.com';
const LOGIN_PATH = '/es/admin/auth/login';

test.describe('Login: solo login (sin register)', () => {
  test('muestra el form de login con email + password + submit', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Iniciar sesión', exact: true })).toBeVisible();

    // No debe haber tab de Registrarse.
    await expect(page.getByRole('tab', { name: /Registrarse/i })).toHaveCount(0);
  });

  test("muestra el link '¿Olvidaste tu contraseña?' apuntando al forgot-password", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    const forgotLink = page.locator("[data-auth-link='forgot']");
    await expect(forgotLink).toBeVisible();
    await expect(forgotLink).toContainText(/Olvidaste/i);
  });

  test("el checkbox 'Recuérdame' está visible", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await expect(page.getByLabel(/Recuérdame/i)).toBeVisible();
  });

  test('muestra botones sociales (Google + Facebook) en admin', async ({ page }) => {
    // sdd/oauth-google-facebook-login · PR 3 inversion.
    // Antes: assert ausencia (pre-PR 1 stub 501). Después: admin login
    // muestra los mismos botones que el storefront, con target
    // data-auth-link para E2E stability.
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await expect(page.locator("[data-auth-link='social-google']")).toBeVisible();
    await expect(page.locator("[data-auth-link='social-facebook']")).toBeVisible();
  });

  test('muestra el selector de idioma (ES/EN)', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    const switcher = page.locator('details summary').first();
    await expect(switcher).toBeVisible();
    await expect(switcher).toContainText(/ES|EN|Español|English/i);
  });

  test('el brand name se muestra en cursiva y sin logo (solo texto)', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    const brand = page.locator('span.italic').filter({ hasText: 'Panda' }).first();
    await expect(brand).toBeVisible();

    // El header NO debe tener un div con icono de rayo (era el icono viejo).
    await expect(page.locator('.bg-\\[\\#2D9D8E\\].rounded-xl').first()).toHaveCount(0);
  });

  test('muestra la ilustración de login en el panel izquierdo', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    const illustration = page.locator("img[alt='Ilustración']").first();
    await expect(illustration).toBeVisible();
  });

  test('POST /api/auth/register devuelve 404 (no existe en admin)', async ({ request }) => {
    const response = await request.post(`${ADMIN_PANDA}/api/auth/register`, {
      data: {
        first_name: 'New',
        last_name: 'User',
        email: 'new-admin@panda-estampados.com',
        password: 'Secure-Pass-2026',
      },
    });

    expect(response.status()).toBe(404);
  });
});

test.describe('Login: branding por tienda', () => {
  test('Panda muestra logo de Panda', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await expect(page).toHaveTitle(/Panda Estampados/i);

    const logo = page.locator(`img[alt="Panda Estampados"]`).first();
    await expect(logo).toBeVisible();
  });

  test('Kitsune muestra logo de Kitsune', async ({ page }) => {
    await page.goto(`${ADMIN_KITSUNE}${LOGIN_PATH}`);

    await expect(page).toHaveTitle(/Kitsune Store/i);

    const logo = page.locator(`img[alt="Kitsune Store"]`).first();
    await expect(logo).toBeVisible();
  });
});

test.describe('Login: autenticación', () => {
  test('login con credenciales default redirige al dashboard', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator('#email').fill('admin@panda-estampados.com');
    await page.locator('#password').fill('password');

    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();

    await page.waitForURL(/\/dashboard/, { timeout: 10_000 });
    await expect(page).toHaveURL(/dashboard/);
  });

  test('login con credenciales inválidas muestra error', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator('#email').fill('wrong@example.com');
    await page.locator('#password').fill('badpassword');

    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 5_000 });
    await expect(page).toHaveURL(/\/es\/admin\/auth\/login/);
  });
});

test.describe('Forgot Password: nuevo design system', () => {
  test("muestra heading 'Recuperar acceso' + email + helper + submit", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/forgot-password`);

    await expect(page.getByRole('heading', { name: 'Recuperar acceso' })).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Enviar enlace de recuperación/i })
    ).toBeVisible();
    await expect(page.getByText(/válido por 30 minutos/i)).toBeVisible();
  });

  test("muestra link 'Volver a iniciar sesión'", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/forgot-password`);

    const backLink = page.locator("[data-auth-link='back']");
    await expect(backLink).toBeVisible();
    await expect(backLink).toContainText(/Volver a iniciar sesión/i);
  });

  test("muestra el alert info '¿No recibes el correo?'", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/forgot-password`);

    await expect(page.getByText(/No recibes el correo/i)).toBeVisible();
  });

  test('submit con email válido llama al backend y redirige a reset-password', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/forgot-password`);
    await page.locator('#email').fill('admin@panda-estampados.com');
    await page.getByRole('button', { name: /Enviar enlace de recuperación/i }).click();

    await page.waitForURL(/\/reset-password/, { timeout: 10_000 });
    await expect(page).toHaveURL(/reset-password/);
  });
});

/**
 * E2E para el flow 2FA (TOTP challenge).
 *
 * Pre-requisito: backend con seed que cree `zuko@panda-estampados.test`
 * con TOTP enrolado (ver `ensureZukoWith2fa` en backend seeders).
 *
 * Cubre:
 *   - happy path: login → /2fa → verify → /admin/dashboard
 *   - invalid code: alert visible, NO navigation
 *   - expired challenge: mensaje + link "Volver a iniciar sesión"
 */
test.describe('Login: 2FA challenge flow', () => {
  // El usuario con TOTP enrolado para tests E2E vive en seeders del backend.
  // El nombre y la secret salen de `ensureZukoWith2fa` (php artisan tinker).
  const TOTP_USER_EMAIL = 'zuko@panda-estampados.test';
  const TOTP_USER_PASSWORD = 'password';

  test('happy path: login → 2fa → dashboard', async ({ page }) => {
    // Skip si E2E_TOTP_SECRET no está configurada. El secret del seeder
    // backend se inyecta vía env var en CI/dev para generar el código TOTP.
    const secret = process.env.E2E_TOTP_SECRET ?? null;
    test.skip(secret === null, 'E2E_TOTP_SECRET not configured');

    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator('#email').fill(TOTP_USER_EMAIL);
    await page.locator('#password').fill(TOTP_USER_PASSWORD);

    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();

    // Espera el redirect al challenge page.
    await page.waitForURL(/\/admin\/auth\/2fa/, { timeout: 10_000 });
    await expect(page).toHaveURL(/\/admin\/auth\/2fa/);

    // Generar código TOTP a partir del secret del seeder vía otplib.
    // El import dinámico evita errores de parse si el package no está.
    // @ts-expect-error otplib no tiene types declarados (proposito del shim
// en src/types/otplib-augment.d.ts), pero no esta instalado en node_modules.
const { authenticator } = (await import('otplib' as never)) as unknown as {
  authenticator: {
    generate: (secret: string) => string;
    verify: (params: { token: string; secret: string }) => boolean;
  };
};
    const code = authenticator.generate(secret as string);

    await page.locator('#totp-code').fill(code);
    await page.getByRole('button', { name: /Verificar código|Verificar code/i }).click();

    await page.waitForURL(/\/dashboard/, { timeout: 10_000 });
    await expect(page).toHaveURL(/dashboard/);
  });

  test('invalid TOTP code muestra alert y NO navega', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator('#email').fill(TOTP_USER_EMAIL);
    await page.locator('#password').fill(TOTP_USER_PASSWORD);

    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();

    await page.waitForURL(/\/admin\/auth\/2fa/, { timeout: 10_000 });

    // Código deliberadamente inválido.
    await page.locator('#totp-code').fill('000000');
    await page.getByRole('button', { name: /Verificar código|Verificar code/i }).click();

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 5_000 });
    await expect(page).toHaveURL(/\/admin\/auth\/2fa/);
  });

  test('challenge expirado (sin token) redirige al login', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/2fa`);

    // Sin `?token=`, la página redirige server-side al login.
    await page.waitForURL(/\/admin\/auth\/login/, { timeout: 5_000 });
    await expect(page).toHaveURL(/login/);
  });
});

test.describe('Storefront (cliente): login + register', () => {
  test('login del cliente muestra CTA signup + selector de idioma', async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}/es/account/auth/login`);

    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();

    // sdd/oauth-google-facebook-login · PR 3 inversion.
    // Storefront ya tenía botones desde antes (discovery #203); el
    // test estaba skipped o falling por colisión de expectations.
    // Inversión explícita para que el e2e suite locke el contrato.
    await expect(page.locator("[data-auth-link='social-google']")).toBeVisible();
    await expect(page.locator("[data-auth-link='social-facebook']")).toBeVisible();

    const signupLink = page.locator("[data-auth-link='signup']");
    await expect(signupLink).toBeVisible();
    await expect(signupLink).toContainText(/Regístrate/i);

    // Language switcher presente.
    const switcher = page.locator('details summary').first();
    await expect(switcher).toBeVisible();
  });

  test('register del cliente muestra nombre + apellido + email + password + strength bar', async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}/es/account/auth/signup`);

    await expect(page.locator('#firstName')).toBeVisible();
    await expect(page.locator('#lastName')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();

    // CTA para volver a login.
    const loginLink = page.locator("[data-auth-link='login']");
    await expect(loginLink).toBeVisible();

    // Ilustración a la DERECHA (el signup invierte el orden).
    const illustration = page.locator("img[alt='Ilustración']").first();
    await expect(illustration).toBeVisible();

    // Strength bar (4 barras).
    const bars = page.locator('.bg-slate-200, .bg-\\[\\#2D9D8E\\]').first();
    await expect(bars).toBeVisible();
  });
});

// sdd/oauth-google-facebook-login · PR 4 (failure UX + flash)
test.describe('OAuth failure flash toast', () => {
  test('oauth_failure_shows_toast_on_login_page', async ({ page }) => {
    // Simulamos un callback que falló con oauth_email_conflict
    // (el backend redirecta a /es/admin/auth/login?flash=oauth_email_conflict).
    await page.goto(`${ADMIN_PANDA}/es/admin/auth/login?flash=oauth_email_conflict`);

    // El toast aparece con data-testid y el código correcto.
    const toast = page.locator("[data-testid='oauth-flash-toast']");
    await expect(toast).toBeVisible();
    await expect(toast).toHaveAttribute('data-flash-code', 'oauth_email_conflict');

    // El texto debe ser distinto de la clave (i18n resuelto).
    const text = await toast.textContent();
    expect(text).not.toBe('auth.login.error.oauth_email_conflict');
    expect(text?.length ?? 0).toBeGreaterThan(0);

    // Después del mount, el query param `flash=` se strippea.
    await expect(page).toHaveURL(/\/es\/admin\/auth\/login(?:\?.*)?$/);
    expect(page.url()).not.toContain('flash=');
  });
});
