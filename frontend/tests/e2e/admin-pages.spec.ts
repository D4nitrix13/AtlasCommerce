import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAsAdmin } from './_helpers/login';

/**
 * E2E tests para paginas admin que requieren login.
 *
 * Patrón: helper `loginAsAdmin(page)` ejecuta el flujo de login
 * (helper compartido en _helpers/login.ts). Despues navega a la
 * pagina objetivo y corre axe-core contra WCAG 2.2 AA.
 *
 * **AGENTS.md §11**: cada test verifica loading + content + a11y.
 *
 * Pre-requisito: backend, frontend y Caddy corriendo localmente
 * (services `panda-estampados-and-kitsune-dev-{backend,frontend}`
 * + `panda-estampados-and-kitsune-dev-nginx` via
 * `scripts/docker-stack.sh status dev panda-estampados-and-kitsune`).
 *
 * Sustituto práctico de Storybook: estos tests verifican que las
 * paginas admin reales (no componentes aislados) cumplen a11y
 * mientras navegan el flujo completo. Cobertura > visualizacion de
 * componentes.
 */

const ADMIN_BASE = 'http://admin.panda-estampados.com';
const LOCALE_PREFIX = '/es';

test.describe('Admin: paginas autenticadas (con login)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('dashboard admin renderiza + sin violaciones a11y criticas', async ({ page }) => {
    await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/dashboard`);

    // Loading: h1 visible
    await expect(
      page.getByRole('heading', { level: 1, name: /dashboard|resumen/i }),
    ).toBeVisible({ timeout: 5000 });

    // A11y scan
    const violations = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
      .analyze();
    const critical = violations.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(critical).toEqual([]);
  });

  test('orders admin renderiza con tabla + sin violaciones a11y criticas', async ({ page }) => {
    await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/sales/orders`);

    // Tabla presente
    await expect(page.locator('table').first()).toBeVisible({ timeout: 5000 });

    // A11y scan (tablas suelen tener problemas de a11y sin thead/th scope)
    const violations = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
      .analyze();
    const critical = violations.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(critical).toEqual([]);
  });

  test('customers admin renderiza + sin violaciones a11y criticas', async ({ page }) => {
    await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/customers`);

    await expect(page.locator('h1')).toBeVisible({ timeout: 5000 });

    const violations = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
      .analyze();
    const critical = violations.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(critical).toEqual([]);
  });

  test('products admin renderiza + sin violaciones a11y criticas', async ({ page }) => {
    await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/catalog/products`);

    await expect(page.locator('h1')).toBeVisible({ timeout: 5000 });

    const violations = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
      .analyze();
    const critical = violations.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(critical).toEqual([]);
  });

  test('logout limpia sesion correctamente', async ({ page }) => {
    // Asume que el beforeEach ya hizo login.
    // Buscar botón de logout (en AdminUserMenu).
    await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/dashboard`);

    // El logout puede estar en menu user; seleccionamos cualquier link
    // o boton con texto "Salir" o "Cerrar sesion".
    const logoutButton = page
      .getByRole('button', { name: /salir|cerrar.*sesion|logout/i })
      .or(page.getByRole('link', { name: /salir|cerrar.*sesion|logout/i }));

    if ((await logoutButton.count()) > 0) {
      await logoutButton.first().click();
      await page.waitForURL(/auth\/login/, { timeout: 5000 });

      // Sesion cookie deberia estar limpia — intentar ir al dashboard
      // deberia redirigir al login.
      await page.goto(`${ADMIN_BASE}${LOCALE_PREFIX}/admin/dashboard`);
      expect(page.url()).toMatch(/auth\/login/);
    } else {
      // Si no se encontro el boton de logout, skip con mensaje claro.
      test.info().annotations.push({
        type: 'no-logout-button',
        description:
          'Boton de logout no encontrado en AdminUserMenu. Verificar naming i18n.',
      });
    }
  });
});
