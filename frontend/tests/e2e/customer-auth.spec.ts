import { test, expect, type Page } from '@playwright/test';

/**
 * E2E tests del customer auth flow (CA.5 — fin del customer-auth
 * sprint, commit 5 de 5).
 *
 * Cubre el flow end-to-end: register desde el storefront → ya logueado
 * (cookie Sanctum stateful) → /account dashboard accesible.
 *
 * Pre-requisitos:
 *  - Backend corriendo en `http://panda-estampados.com` (host usado
 *    por el form público, ver `getServerByHost` en el middleware
 *    `ResolveStoreFromDomain`).
 *  - Frontend corriendo y accesible.
 *  - Customer no autenticado al inicio (clean cookies).
 *
 * Diseño: el test REGISTRA un customer nuevo via el form (no usa
 * seed data) para verificar el flow completo: form → backend → cookie
 * Sanctum stateful → dashboard. Después cierra sesión y verifica
 * el flow de re-login.
 *
 * Cleanup: cada test genera un email único (`random-uuid@...`) para
 * no chocar entre ejecuciones ni con customers seedeados.
 */

const FRONTEND_BASE = 'http://panda-estampados.com';
const FRONTEND_LOCALE = 'es';

function randomEmail(): string {
  return `ca5-test-${crypto.randomUUID()}@panda-estampados.test`;
}

async function gotoRegister(page: Page): Promise<void> {
  await page.goto(`${FRONTEND_BASE}/${FRONTEND_LOCALE}/account/signup`);
  await page.waitForURL(/\/account\/signup/);
}

async function fillRegisterForm(
  page: Page,
  opts: { email: string; firstName: string; lastName: string; password: string; phone?: string },
): Promise<void> {
  await page.getByLabel('auth.signup.shell.firstNameLabel').fill(opts.firstName);
  await page.getByLabel('auth.signup.shell.lastNameLabel').fill(opts.lastName);
  await page.getByLabel('auth.signup.shell.emailLabel').fill(opts.email);
  await page.getByLabel('auth.signup.shell.passwordLabel').fill(opts.password);
  await page.getByLabel('auth.signup.shell.passwordConfirmationLabel').fill(opts.password);
  if (opts.phone !== undefined && opts.phone !== '') {
    await page.getByLabel('auth.signup.shell.phoneLabel').fill(opts.phone);
  }
  await page.getByLabel('auth.signup.shell.termsAccept').check();
}

test.describe('CA.5 — customer auth flow end-to-end', () => {
  test('register crea customer + cookie Sanctum + navega a /account', async ({ page, context }) => {
    const email = randomEmail();
    const password = 'SecurePass1234';

    await gotoRegister(page);
    await fillRegisterForm(page, {
      email,
      firstName: 'Cliente',
      lastName: 'CA5',
      password,
    });

    await page.getByRole('button', { name: 'auth.signup.shell.submit' }).click();

    // El register redirige a /account (callbackPath default).
    // El AccountAuthGuard verifica Sanctum stateful cookie y deja
    // pasar (vs. redirigir a login).
    await page.waitForURL(/\/account(?!\/signup|\/login|\/forgot)/, { timeout: 10_000 });
    expect(page.url()).toMatch(/\/es\/account/);
    expect(page.url()).not.toMatch(/\/signup|\/login/);

    // Verificar que la sesión está activa via cookies de Sanctum
    // stateful. csrf-cookie + XSRF-TOKEN + session cookie.
    const cookies = await context.cookies();
    const hasSession = cookies.some((c) => c.name === 'ecommerce-dev-session' || c.name.includes('session'));
    const hasXsrf = cookies.some((c) => c.name === 'XSRF-TOKEN');
    expect(hasSession || hasXsrf).toBe(true);
  });

  test('login desde form con customer recién registrado funciona', async ({ page, context }) => {
    const email = randomEmail();
    const password = 'SecurePass1234';

    // Step 1: register via form (establece cookie).
    await gotoRegister(page);
    await fillRegisterForm(page, {
      email,
      firstName: 'Cliente',
      lastName: 'CA5-Login',
      password,
    });
    await page.getByRole('button', { name: 'auth.signup.shell.submit' }).click();
    await page.waitForURL(/\/es\/account/, { timeout: 10_000 });

    // Step 2: navegar a login (vaciando cookies primero para simular
    // un user nuevo en el mismo browser).
    await context.clearCookies();
    await page.goto(`${FRONTEND_BASE}/${FRONTEND_LOCALE}/account/login`);

    // Step 3: llenar form y submit.
    await page.getByLabel('auth.login.shell.emailLabel').fill(email);
    await page.getByLabel('auth.login.shell.passwordLabel').fill(password);
    await page.getByRole('button', { name: 'auth.login.shell.submit' }).click();

    // Step 4: redirige a /account (callbackPath default).
    await page.waitForURL(/\/es\/account(?!\/login|\/signup)/, { timeout: 10_000 });
    expect(page.url()).not.toMatch(/\/login/);
  });

  test('password mismatch muestra error y no envía al backend', async ({ page }) => {
    const email = randomEmail();

    await gotoRegister(page);
    await fillRegisterForm(page, {
      email,
      firstName: 'Cliente',
      lastName: 'CA5-Mismatch',
      password: 'SecurePass1234',
    });
    // Sobreescribir el campo de confirmación con un valor distinto.
    await page.getByLabel('auth.signup.shell.passwordConfirmationLabel').fill('DifferentPass1234');

    await page.getByRole('button', { name: 'auth.signup.shell.submit' }).click();

    // El form valida cliente-side y muestra error. El usuario NO se
    // registra (no se crea customer en el backend).
    await expect(page.getByRole('alert')).toBeVisible();
    expect(page.url()).toMatch(/\/signup/);
  });

  test('email duplicado muestra error del backend', async ({ page }) => {
    const email = randomEmail();
    const password = 'SecurePass1234';

    // Primer register — debe pasar.
    await gotoRegister(page);
    await fillRegisterForm(page, {
      email,
      firstName: 'Duplicado',
      lastName: 'Test1',
      password,
    });
    await page.getByRole('button', { name: 'auth.signup.shell.submit' }).click();
    await page.waitForURL(/\/es\/account/, { timeout: 10_000 });

    // Limpiar cookies (logout) y re-register con el mismo email.
    await page.context().clearCookies();
    await gotoRegister(page);
    await fillRegisterForm(page, {
      email,
      firstName: 'Duplicado',
      lastName: 'Test2',
      password,
    });
    await page.getByRole('button', { name: 'auth.signup.shell.submit' }).click();

    // El backend retorna 422 con error de email duplicado.
    // El form lo muestra en el alert role.
    await expect(page.getByRole('alert')).toBeVisible();
    expect(page.url()).toMatch(/\/signup/);
  });
});
