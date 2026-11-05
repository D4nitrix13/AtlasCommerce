import { type Page, type BrowserContext } from '@playwright/test';

/**
 * Helper compartido para autenticar como admin en E2E tests.
 *
 * **Credenciales de test** (seed `RecoveryCodesTestSeeder` — no existe
 * un `AdminUserSeeder`; la password es su `DEFAULT_PASSWORD`):
 *
 * - Email: `admin@panda-estampados.test`
 * - Password: `password`
 *
 * OJO: este usuario tiene 2FA TOTP activo. El login responde
 * `2fa_required` y hay que resolver el challenge en
 * `/es/account/2fa?token=<id>` antes de entrar al admin.
 *
 * Si los credenciales cambian, actualizar este helper. NO hardcodear
 * en cada spec — usar `loginAsAdmin(page)`.
 *
 * @example
 * ```ts
 * import { loginAsAdmin } from './_helpers/login';
 *
 * test('dashboard admin renderiza', async ({ page }) => {
 *   await loginAsAdmin(page);
 *   await page.goto('/es/admin/dashboard');
 *   expect(...);
 * });
 * ```
 */
export const ADMIN_TEST_EMAIL = 'admin@panda-estampados.test';
export const ADMIN_TEST_PASSWORD = 'password';

/**
 * Loguea como admin en `admin.panda-estampados.com` y espera a que
 * la navegacion post-login se complete (cookies de sesion
 * persistidas en el storage del BrowserContext del caller).
 *
 * @param  page  Page de Playwright que sera redirigida al dashboard
 *               tras login exitoso
 * @param  context  BrowserContext opcional para escenarios donde el
 *                  caller quiere reusar el storage entre specs (ej:
 *                  tests con `storageState`). No requerido para uso
 *                  basico.
 */
export async function loginAsAdmin(
  page: Page,
  options: { baseUrl?: string } = {},
): Promise<void> {
  const baseUrl = options.baseUrl ?? 'http://admin.panda-estampados.com';
  const loginUrl = `${baseUrl}/es/admin/auth/login`;

  await page.goto(loginUrl);
  await page.locator('#email').fill(ADMIN_TEST_EMAIL);
  await page.locator('#password').fill(ADMIN_TEST_PASSWORD);
  await page.locator('button[type="submit"]').click();

  // Esperar a que la navegacion post-login complete. Dashboard es la
  // ruta default despues de login OK.
  await page.waitForURL(/\/es\/admin\/dashboard/, { timeout: 10_000 });
}

/**
 * Helper para tests que requieren login previo via storageState.
 *
 * Caso de uso: spec de Playwright con `test.use({ storageState: ... })`
 * para que el login corra UNA vez y se reuse en todos los tests del
 * describe.
 *
 * Crea un contexto fresco, hace login, y devuelve el `storageState` listo
 * para `test.use({ storageState })`.
 *
 * @example
 * ```ts
 * let adminStorage: Awaited<ReturnType<typeof getAdminStorageState>>;
 * test.beforeAll(async ({ browser }) => {
 *   adminStorage = await getAdminStorageState(browser);
 * });
 * test.describe('admin pages', () => {
 *   test.use({ storageState: adminStorage });
 *   test('dashboard', async ({ page }) => { ... });
 * });
 * ```
 */
export async function getAdminStorageState(
  context: BrowserContext,
  options: { baseUrl?: string } = {},
): Promise<{ cookies: Array<{ name: string; value: string; domain: string; path: string; expires: number; httpOnly: boolean; secure: boolean; sameSite: 'Strict' | 'Lax' | 'None' }>; origins: Array<{ origin: string; localStorage: Array<{ name: string; value: string }> }> }> {
  const page = await context.newPage();
  try {
    await loginAsAdmin(page, options);
    return await context.storageState();
  } finally {
    await page.close();
  }
}
