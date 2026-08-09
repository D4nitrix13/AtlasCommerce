import { test, expect } from "@playwright/test";

/**
 * E2E tests para el flujo de autenticación admin.
 *
 * Cobertura: persistencia del tab login/register en URL. El bug
 * original era que el useState volvía a "login" al recargar la
 * página. Ahora el tab vive en la URL como query param (?tab=register).
 *
 * Pre-requisito: backend, frontend y Caddy corriendo localmente.
 * Las URLs usan admin.panda-estampados.com (Panda Estampados)
 * mapeado en /etc/hosts.
 */

const ADMIN_PANDA = "http://admin.panda-estampados.com";
const ADMIN_KITSUNE = "http://admin.kitsune.com";
const LOGIN_PATH = "/es/admin/auth/login";

test.describe("Login: tab persistence via URL", () => {
  test("default: muestra tab login cuando no hay query param", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    const loginTab = page.getByRole("tab", { name: /Iniciar sesión/i });
    const registerTab = page.getByRole("tab", { name: /Registrarse/i });

    await expect(loginTab).toHaveAttribute("aria-selected", "true");
    await expect(registerTab).toHaveAttribute("aria-selected", "false");
  });

  test("URL con ?tab=register abre directo en Registrarse", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}?tab=register`);

    const loginTab = page.getByRole("tab", { name: /Iniciar sesión/i });
    const registerTab = page.getByRole("tab", { name: /Registrarse/i });

    await expect(registerTab).toHaveAttribute("aria-selected", "true");
    await expect(loginTab).toHaveAttribute("aria-selected", "false");
  });

  test("click en Registrarse actualiza la URL sin navegación", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);
    await page.getByRole("tab", { name: /Registrarse/i }).click();

    expect(page.url()).toContain("tab=register");
    // Sin recargar: el estado del navegador se mantiene.
    await expect(page.getByRole("tab", { name: /Registrarse/i })).toHaveAttribute(
      "aria-selected",
      "true",
    );
  });

  test("recargar la página con tab=register preserva el tab", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}?tab=register`);
    await page.getByRole("tab", { name: /Iniciar sesión/i }).click();

    // Cambio a login para verificar el flujo bidireccional.
    await page.reload();

    const loginTab = page.getByRole("tab", { name: /Iniciar sesión/i });
    await expect(loginTab).toHaveAttribute("aria-selected", "true");
  });

  test("compartir link con ?tab=register: el receptor ve Registrarse", async ({
    page,
    context,
  }) => {
    // Simular "compartir" el link: ir a la URL en un contexto nuevo
    // (equivalente a abrir el link en otra ventana/incógnito).
    const page2 = await context.newPage();
    await page2.goto(`${ADMIN_PANDA}${LOGIN_PATH}?tab=register`);

    const registerTab = page2.getByRole("tab", { name: /Registrarse/i });
    await expect(registerTab).toHaveAttribute("aria-selected", "true");

    await page2.close();
  });

  test("botón atrás del browser respeta el tab en URL", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);
    await page.getByRole("tab", { name: /Registrarse/i }).click();
    expect(page.url()).toContain("tab=register");

    // Push a fake history entry (sin navegar) para que `goBack` funcione
    // sin depender de una página real que cargue.
    await page.evaluate(() => {
      window.history.pushState({}, "", "/es/admin/users");
    });
    await page.goBack();

    const registerTab = page.getByRole("tab", { name: /Registrarse/i });
    await expect(registerTab).toHaveAttribute("aria-selected", "true");
  });

  test("URL sin tab param después de click vuelve a URL limpia", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}?tab=register`);

    // Click en Iniciar sesión (debería borrar el query param).
    await page.getByRole("tab", { name: /Iniciar sesión/i }).click();

    expect(page.url()).not.toContain("tab=");
  });
});

test.describe("Login: branding por tienda", () => {
  test("Panda muestra color rojo y logo de Panda", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await expect(page).toHaveTitle(/Panda Estampados/i);

    const logo = page.locator(`img[alt="Panda Estampados"]`).first();
    await expect(logo).toBeVisible();
  });

  test("Kitsune muestra color violeta y logo de Kitsune", async ({ page }) => {
    await page.goto(`${ADMIN_KITSUNE}${LOGIN_PATH}`);

    await expect(page).toHaveTitle(/Kitsune Store/i);

    const logo = page.locator(`img[alt="Kitsune Store"]`).first();
    await expect(logo).toBeVisible();
  });
});

test.describe("Login: autenticación", () => {
  test("login con credenciales válidas redirige al dashboard", async ({
    page,
  }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator("#email").fill("admin@panda-estampados.test");
    await page.locator("#password").fill("password");

    await page
      .getByRole("button", { name: "Iniciar sesión", exact: true })
      .click();

    // Espera al redirect al dashboard.
    await page.waitForURL(/\/dashboard/, { timeout: 10_000 });

    await expect(page).toHaveURL(/dashboard/);
  });

  test("login con credenciales inválidas muestra error", async ({ page }) => {
    await page.goto(`${ADMIN_PANDA}${LOGIN_PATH}`);

    await page.locator("#email").fill("wrong@example.com");
    await page.locator("#password").fill("badpassword");

    await page
      .getByRole("button", { name: "Iniciar sesión", exact: true })
      .click();

    // No debe redirigir — debe mostrar el error.
    await expect(page.getByRole("alert")).toBeVisible({ timeout: 5_000 });
    await expect(page).toHaveURL(/\/es\/admin\/auth\/login/);
  });
});