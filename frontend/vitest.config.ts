/// <reference types="vitest" />
import { defineConfig } from "vitest/config";
import react from "@astrojs/react";

export default defineConfig({
  plugins: [react()],

  define: {
    // Variables públicas que el código lee via `import.meta.env`.
    "import.meta.env.PUBLIC_API_URL": JSON.stringify(
      "http://localhost:8000/api",
    ),
  },

  test: {
    globals: true,
    environment: "jsdom",
    setupFiles: ["./tests/setup.ts"],
    css: true,
    include: ["tests/unit/**/*.{test,spec}.{ts,tsx}"],
    exclude: ["node_modules", "dist", ".astro", "tests/e2e/**"],
    coverage: {
      provider: "v8",
      reporter: ["text", "html", "lcov"],
      // REM viewer (NFR-05) gates: `lib/rem/**` + `components/rem/**`
      // must stay above 85% lines. We list the REM-specific paths
      // alongside the broader `src/**/*.{ts,tsx}` so coverage
      // measurement is exhaustive for the whole frontend while the
      // thresholds stay tight.
      include: ["src/**/*.{ts,tsx}", "src/lib/rem/**", "src/components/rem/**"],
      exclude: [
        "src/**/*.d.ts",
        "src/types/**",
        "src/i18n/server.ts",
        "src/**/index.ts",
      ],
      thresholds: {
        lines: 85,
        functions: 85,
        branches: 80,
        statements: 85,
        perFile: false,
      },
    },
  },
});