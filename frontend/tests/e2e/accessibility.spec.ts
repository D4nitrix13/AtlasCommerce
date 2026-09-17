import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Tests de accesibilidad WCAG 2.2 AA con axe-core (RF-USA-043).
 *
 * Ejecuta `axe-core` (la lib estándar de facto para a11y) contra las
 * páginas más críticas del storefront y admin. Falla si hay violaciones
 * serious o critical.
 *
 * Standards chequeados: WCAG 2.2 Level A + AA + AAA best practices.
 * Tags: wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa.
 *
 * Pre-requisito: backend + frontend + Caddy corriendo localmente.
 * Las URLs usan los hostnames de /etc/hosts.
 *
 * Documentación: docs/frontend/accessibility-tests.md.
 */

const STOREFRONT_BASE = 'http://panda-estampados.com';
const ADMIN_BASE = 'http://admin.panda-estampados.com';

/**
 * Helper: corre axe-core sobre la página actual y devuelve las
 * violaciones serious/critical (las que bloquean WCAG).
 */
async function getCriticalViolations(page: Page): Promise<unknown[]> {
    const accessibilityScanResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
        .analyze();

    return accessibilityScanResults.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
    );
}

test.describe('Storefront: accesibilidad WCAG 2.2 AA', () => {
    test('homepage sin violaciones críticas', async ({ page }) => {
        await page.goto(`${STOREFRONT_BASE}/es`);

        const violations = await getCriticalViolations(page);

        // Reportar contexto para debugging si falla.
        if (violations.length > 0) {
            console.error(
                'Violaciones encontradas:',
                JSON.stringify(violations, null, 2),
            );
        }

        expect(violations).toEqual([]);
    });

    test('página de login sin violaciones críticas', async ({ page }) => {
        await page.goto(`${STOREFRONT_BASE}/es/auth/login`);

        const violations = await getCriticalViolations(page);
        expect(violations).toEqual([]);
    });

    test('página de producto sin violaciones críticas', async ({ page }) => {
        // Slug genérico — ajustá si tu producto canónico tiene otro nombre.
        await page.goto(`${STOREFRONT_BASE}/es/products/panda-zen`);

        const violations = await getCriticalViolations(page);
        expect(violations).toEqual([]);
    });
});

test.describe('Admin: accesibilidad WCAG 2.2 AA', () => {
    test('login admin sin violaciones críticas', async ({ page }) => {
        await page.goto(`${ADMIN_BASE}/es/admin/auth/login`);

        const violations = await getCriticalViolations(page);
        expect(violations).toEqual([]);
    });

    test('forgot password admin sin violaciones críticas', async ({ page }) => {
        await page.goto(`${ADMIN_BASE}/es/admin/auth/forgot-password`);

        const violations = await getCriticalViolations(page);
        expect(violations).toEqual([]);
    });
});
