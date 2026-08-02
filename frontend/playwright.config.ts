import { defineConfig, devices } from "@playwright/test";

/**
 * Playwright config para E2E tests del frontend.
 *
 * Pre-requisito: tener corriendo en local:
 *   - Postgres + Redis (docker compose up -d en docker/dev)
 *   - Backend Laravel (php artisan serve --host=127.0.0.1 --port=8000)
 *   - Frontend Astro (pnpm run dev)
 *   - Caddy (sudo systemctl start caddy) en puerto 80
 *
 * Las URLs base usan `pandas-estampados.com` que debe estar en /etc/hosts.
 * Si los servicios no están corriendo, los tests fallan con error
 * claro de conexión.
 */
export default defineConfig({
  testDir: "./tests/e2e",

  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,

  reporter: process.env.CI ? "github" : "list",

  use: {
    baseURL: "http://pandas-estampados.com",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
  },

  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],

  // `webServer` queda comentado porque asumimos que el dev environment
  // está corriendo. Para correr todo aislado:
  //   1. npm run dev (backend + frontend + caddy)
  //   2. npx playwright test
  //
  // webServer: {
  //   command: "pnpm run dev:e2e",
  //   url: "http://pandas-estampados.com",
  //   reuseExistingServer: !process.env.CI,
  //   timeout: 60_000,
  // },
});