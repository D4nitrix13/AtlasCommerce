// @ts-check
import { defineConfig } from "astro/config";
import tailwindcss from "@tailwindcss/vite";
import react from "@astrojs/react";
import node from "@astrojs/node";

// https://astro.build/config
//
// NOTA: vite-plugin-svgo no se usa porque los SVGs de este proyecto
// viven en `public/images/**/*.svg` y se referencian con `<img src=...>`
// desde componentes Astro/React. SVGO como plugin de Vite solo optimiza
// los SVGs que se importan via `import` (no los referenciados por URL).
// Para SVGs en /public/, el script `pnpm run optimize:svgs` (wired al
// prebuild) cubre todo. Ver /docs/IMAGE_OPTIMIZATION.md para detalles.
export default defineConfig({
  vite: {
    plugins: [tailwindcss()],
    server: {
      // Hosts permitidos para dev con Caddy local + subdominios.
      // En prod cada subdominio resuelve via DNS real, no hace
      // falta esta config.
      allowedHosts: [
        "panda-estampados.com",
        "www.panda-estampados.com",
        "admin.panda-estampados.com",
        "kitsune.com",
        "www.kitsune.com",
        "admin.kitsune.com",
        "localhost",
        "admin.localhost",
      ],
    },
  },

  integrations: [react()],

  // Adapter Node SSR + output server. Necesario para SSR real (los
  // componentes admin leen headers, sesión, etc.).
  adapter: node({ mode: "standalone" }),
  output: "server",

  // Ruteo i18n nativo vía carpetas dinámicas `[locale]/`.
  // Las páginas admin viven en `src/pages/[locale]/admin/...` y
  // Astro genera automáticamente `/es/...` y `/en/...` para cada locale.
  // El middleware solo se encarga de redirect desde URLs sin prefix.
  i18n: {
    defaultLocale: "es",
    locales: ["es", "en"],
    routing: {
      prefixDefaultLocale: true,
      redirectToDefaultLocale: false,
    },
  },
});