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
// Para SVGs en /public/, el script `bun run optimize:svgs` (wired al
// prebuild) cubre todo. Ver /docs/frontend/image-optimization.md para detalles.

// Whitelist de hosts permitidos por Vite para dev con Caddy local + TLD
// `.test` (RFC 2606) y subdominios www./admin. En prod cada subdominio
// resuelve via DNS real, no hace falta esta config (Vite no se usa en
// runtime de prod, solo en build/dev). Si agregas un tenant a
// `scripts/docker/_lib/resolve.sh` (array STORES), agregalo tambien aca.
const TENANT_HOSTS = [
  // panda-estampados-and-kitsune (multi-tenant: panda-estampados + kitsune)
  "panda-estampados.com",
  "www.panda-estampados.com",
  "admin.panda-estampados.com",
  "kitsune.com",
  "www.kitsune.com",
  "admin.kitsune.com",
  // mm-boutique
  "mm-boutique.com",
  "www.mm-boutique.com",
  "admin.mm-boutique.com",
  // morales-store
  "morales-store.com",
  "www.morales-store.com",
  "admin.morales-store.com",
  // instrumentos-musicales-la-voz (primer cliente real SaaS, La Voz)
  "instrumentos-musicales-la-voz.test",
  "www.instrumentos-musicales-la-voz.test",
  "admin.instrumentos-musicales-la-voz.test",
  // TLD .test local (RFC 2606) para cada tienda
  "panda-estampados-and-kitsune.test",
  "www.panda-estampados-and-kitsune.test",
  "admin.panda-estampados-and-kitsune.test",
  "mm-boutique.test",
  "www.mm-boutique.test",
  "admin.mm-boutique.test",
  "morales-store.test",
  "www.morales-store.test",
  "admin.morales-store.test",
  // Hosts locales (sin subdominio de tienda)
  "localhost",
  "admin.localhost",
  "127.0.0.1",
];

export default defineConfig({
  vite: {
    plugins: [tailwindcss()],
    server: {
      allowedHosts: TENANT_HOSTS,
      // Las URLs del API y de las imágenes de producto son relativas
      // (/api/...). En dev el dev server no las proxyea solo y se
      // resuelven contra Astro → 404 → imágenes rotas. El proxy las
      // manda al backend; en prod las sirve el mismo origin.
      proxy: {
        "/api": {
          target: process.env.INTERNAL_API_ORIGIN ?? "http://localhost:9000",
          // Conservar el Host original: el middleware de tenant del
          // backend resuelve por store_domains.domain == host.
          changeOrigin: false,
        },
      },
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