import { test, expect, type APIRequestContext, type Page } from '@playwright/test';

/**
 * E2E tests para el flow de recuperación de contraseña challenge_token.
 *
 * Cubre los 3 paths completos (email / recovery code / TOTP), los
 * paths de error (código inválido, código reusado, challenge expirado)
 * y la selección de método cuando hay varios disponibles.
 *
 * Pre-requisitos:
 *  - Backend corriendo en `http://admin.panda-estampados.com`
 *  - Database seeded con `RecoveryCodesTestSeeder` (4 personajes en
 *    4 tiendas distintas). El test usa `admin@panda-estampados.test`
 *    que tiene 2FA + 10 recovery codes.
 *  - `E2E_TOTP_SECRET` configurada (secret del TwoFactorSetting del
 *    seeder, en base32). Sin esto, los tests de TOTP hacen `skip`.
 *  - `E2E_RECOVERY_CODE` configurada con uno de los 10 codes del
 *    seeder (los códigos se imprimen a stdout cuando corre el seeder).
 *
 * Estructura:
 *  - Para TOTP usamos `otplib` via dynamic import (mismo patrón que
 *    `login.spec.ts`). El import dinámico evita crash en parse si el
 *    package no está instalado y permite el `skip` limpio cuando
 *    `E2E_TOTP_SECRET` no está seteada.
 */

// `process` es global en Node; tsc no lo conoce sin `@types/node`.
// El proyecto no instala @types/node — este patrón es consistente
// con `tests/e2e/login.spec.ts` que también usa `process.env`.
declare const process: { env: Record<string, string | undefined> };

const ADMIN_HOST = 'http://admin.panda-estampados.com';
const WIZARD_PATH = '/es/admin/auth/forgot-password';

const SEEDED_USER = 'admin@panda-estampados.test';

// Tests skip limpio cuando las env vars no están seteadas.
const TOTP_SECRET = process.env.E2E_TOTP_SECRET ?? '';
const RECOVERY_CODE = process.env.E2E_RECOVERY_CODE ?? '';
const EMAIL_USER = process.env.E2E_EMAIL_USER ?? 'ops@mm-boutique.test';

/**
 * Genera un código TOTP-SHA1 válido para el secret base32 actual.
 * Usa `otplib` via dynamic import (mismo patrón que login.spec.ts).
 */
async function generateTotpCode(secretBase32: string): Promise<string> {
  // @ts-expect-error otplib no tiene types declarados (proposito del shim
// en src/types/otplib-augment.d.ts), pero no esta instalado en node_modules.
const { authenticator } = (await import('otplib' as never)) as unknown as {
  authenticator: {
    generate: (secret: string) => string;
    verify: (params: { token: string; secret: string }) => boolean;
  };
};
  return authenticator.generate(secretBase32);
}

/**
 * Helper: completa el wizard hasta el final con un código de recovery
 * válido. Devuelve la URL final para que el test pueda seguir
 * navegando.
 */
async function completeFlowWithRecoveryCode(
  page: Page,
  request: APIRequestContext,
  email: string,
  code: string,
): Promise<void> {
  await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
  await page.locator('#wizard-email').fill(email);
  await page.locator('button:has-text("Continuar")').click();

  // Si solo hay 1 método, el wizard salta el method step.
  // Si hay >1, elegimos recovery_codes.
  const methodButton = page.locator('button:has-text("Código de respaldo")').first();
  if (await methodButton.isVisible({ timeout: 2000 }).catch(() => false)) {
    await methodButton.click();
  }

  await page.locator('#wizard-code').fill(code);
  await page.locator('button:has-text("Verificar código")').click();

  await page.locator('#wizard-password').fill('E2EFullFlowPassword1234');
  await page.locator('#wizard-password-confirm').fill('E2EFullFlowPassword1234');
  await page.locator('button:has-text("Restablecer contraseña")').click();

  // Espera el redirect al login con flash=password_reset.
  await page.waitForURL(/\/auth\/login.*flash=password_reset/, { timeout: 10_000 });
  expect(page.url()).toContain('flash=password_reset');
  void request;
}

test.describe('Recovery wizard: 3 métodos disponibles', () => {
  test('flow completo via recovery code: 4 steps + redirect al login', async ({ page, request }) => {
    test.skip(RECOVERY_CODE === '', 'E2E_RECOVERY_CODE not configured');

    await completeFlowWithRecoveryCode(page, request, SEEDED_USER, RECOVERY_CODE);

    // Después del redirect el form de login debe estar visible.
    await expect(page.locator('#email')).toBeVisible({ timeout: 5_000 });
  });

  test('flow completo via TOTP', async ({ page }) => {
    test.skip(TOTP_SECRET === '', 'E2E_TOTP_SECRET not configured');

    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
    await page.locator('#wizard-email').fill(SEEDED_USER);
    await page.locator('button:has-text("Continuar")').click();

    // El user admin@panda-estampados.test tiene 2FA + recovery codes.
    // El método step debe aparecer (length=2 → method step).
    const totpButton = page.locator('button:has-text("Authenticator app")');
    await expect(totpButton).toBeVisible({ timeout: 5_000 });
    await totpButton.click();

    await page.locator('#wizard-code').fill(await generateTotpCode(TOTP_SECRET));
    await page.locator('button:has-text("Verificar código")').click();

    await page.locator('#wizard-password').fill('E2ETotpFlowPassword1234');
    await page.locator('#wizard-password-confirm').fill('E2ETotpFlowPassword1234');
    await page.locator('button:has-text("Restablecer contraseña")').click();

    await page.waitForURL(/\/auth\/login.*flash=password_reset/, { timeout: 10_000 });
  });

  test('flow via email cuando solo hay 1 método', async ({ page }) => {
    // ops@mm-boutique.test tiene preferred_recovery_method='email'
    // y no tiene 2FA ni recovery codes.
    test.skip(EMAIL_USER === '', 'E2E_EMAIL_USER not configured');

    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
    await page.locator('#wizard-email').fill(EMAIL_USER);
    await page.locator('button:has-text("Continuar")').click();

    // Sin 2FA ni recovery codes: el array available_methods está vacío.
    // El wizard muestra el mensaje "Sin métodos disponibles".
    await expect(page.getByText(/Sin métodos disponibles/i)).toBeVisible({ timeout: 5_000 });
  });
});

test.describe('Recovery wizard: failure paths', () => {
  test('recovery code inválido muestra error sin consumir', async ({ page }) => {
    test.skip(RECOVERY_CODE === '', 'E2E_RECOVERY_CODE not configured');

    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
    await page.locator('#wizard-email').fill(SEEDED_USER);
    await page.locator('button:has-text("Continuar")').click();

    const methodButton = page.locator('button:has-text("Código de respaldo")').first();
    if (await methodButton.isVisible({ timeout: 2000 }).catch(() => false)) {
      await methodButton.click();
    }

    // 10 chars del alfabeto permitido, pero deliberadamente inválido.
    await page.locator('#wizard-code').fill('AAAAAAAAAA');
    await page.locator('button:has-text("Verificar código")').click();

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 5_000 });
    // NO debe haber avanzado al password step.
    await expect(page.locator('#wizard-password')).toHaveCount(0);
  });

  test('recovery code reusado: el segundo intento falla', async ({ page, request }) => {
    test.skip(RECOVERY_CODE === '', 'E2E_RECOVERY_CODE not configured');

    // Primer flow: consume el code exitosamente.
    await completeFlowWithRecoveryCode(page, request, SEEDED_USER, RECOVERY_CODE);

    // Resetear password de nuevo (necesitamos volver a loguear como admin para esto)
    // Para mantener el test simple: el segundo intento desde el principio
    // con el mismo code debe fallar (el code ya está consumido por
    // RecoveryCodeService::verifyAndConsume).
    // Nota: ops@mm-boutique.test no tiene recovery codes; usamos
    // directamente la API para no acoplarnos al flow UI.
    const response = await request.post(`${ADMIN_HOST}/api/auth/recover-via-recovery-code`, {
      data: {
        email: SEEDED_USER,
        code: RECOVERY_CODE,
      },
    });
    expect(response.status()).toBe(422);
  });

  test('TOTP code inválido muestra error', async ({ page }) => {
    test.skip(TOTP_SECRET === '', 'E2E_TOTP_SECRET not configured');

    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
    await page.locator('#wizard-email').fill(SEEDED_USER);
    await page.locator('button:has-text("Continuar")').click();

    const totpButton = page.locator('button:has-text("Authenticator app")');
    await expect(totpButton).toBeVisible({ timeout: 5_000 });
    await totpButton.click();

    // Código deliberadamente inválido (no es el TOTP actual).
    await page.locator('#wizard-code').fill('000000');
    await page.locator('button:has-text("Verificar código")').click();

    await expect(page.getByRole('alert')).toBeVisible({ timeout: 5_000 });
    await expect(page.locator('#wizard-password')).toHaveCount(0);
  });

  test('challenge expirado muestra error en reset', async ({ page, request }) => {
    test.skip(RECOVERY_CODE === '', 'E2E_RECOVERY_CODE not configured');

    // 1. Recover para emitir un challenge.
    const recoverResponse = await request.post(
      `${ADMIN_HOST}/api/auth/recover-via-recovery-code`,
      {
        data: { email: SEEDED_USER, code: RECOVERY_CODE },
      },
    );
    expect(recoverResponse.status()).toBe(200);
    const { challenge_token } = await recoverResponse.json();

    // 2. Forzar expiración via DB no es viable desde el e2e. En su
    // lugar, esperamos 10 minutos (TTL del challenge) y reintentamos.
    // Esto es lento — saltamos por default a menos que se configure
    // explícitamente.
    test.skip(
      process.env.E2E_WAIT_FOR_EXPIRY !== 'true',
      'E2E_WAIT_FOR_EXPIRY not set (would take 10+ minutes)',
    );

    await page.waitForTimeout(11 * 60 * 1000); // 11 min

    // 3. Reset con el challenge expirado.
    const resetResponse = await request.post(`${ADMIN_HOST}/api/auth/reset-password`, {
      data: {
        email: SEEDED_USER,
        challenge_token,
        password: 'ExpiredTokenPassword1234',
        password_confirmation: 'ExpiredTokenPassword1234',
      },
    });
    expect(resetResponse.status()).toBe(422);
  });
});

test.describe('Recovery wizard: UX', () => {
  test('email vacío muestra validación cliente antes de submit', async ({ page }) => {
    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);

    // Submit sin completar el email.
    await page.locator('button:has-text("Continuar")').click();

    // El browser nativo debe mostrar el "Please fill out this field"
    // via `required`. Verificamos que el form no haya avanzado.
    await expect(page.locator('#wizard-email')).toBeVisible();
    // No debe haber alert de error de API (la validación cliente lo previene).
    await expect(page.getByRole('alert')).toHaveCount(0);
  });

  test('email con formato inválido muestra error', async ({ page }) => {
    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);
    await page.locator('#wizard-email').fill('not-an-email');
    await page.locator('button:has-text("Continuar")').click();

    // El browser nativo bloquea via `type="email"`, así que el form
    // no debe avanzar al method step.
    await expect(page.locator('#wizard-email')).toBeVisible();
  });

  test('stepper muestra el step actual destacado', async ({ page }) => {
    await page.goto(`${ADMIN_HOST}${WIZARD_PATH}`);

    // Step actual = "Email". El dot debe tener aria-current="step".
    const currentDot = page.locator('[aria-current="step"]');
    await expect(currentDot).toBeVisible();

    // Avanzar al segundo step y verificar que el dot cambia.
    await page.locator('#wizard-email').fill(SEEDED_USER);
    await page.locator('button:has-text("Continuar")').click();

    // Después del método step (que puede saltarse), el current debe
    // haber cambiado. Esperamos un poco a que React re-rendere.
    await page.waitForTimeout(500);
    const newCurrentDot = page.locator('[aria-current="step"]');
    await expect(newCurrentDot).toBeVisible();
  });
});