# Decisión — Render del listado de productos del storefront (CSR vs SSR)

> **Estado:** vigente. **Fecha:** 2026-10-20. **Owner:** frontend.

## Decisión

El listado de productos del storefront
(`pages/[locale]/products/index.astro`) renderiza vía **CSR
(Client-Side Rendering)**: una island React que hace fetch al API en el
cliente. NO usa SSR.

## Por qué

El SSR anterior (commit `a8e478d5`) hacía `fetch` server-side a
`/v1/storefront/products` y producía dos problemas operacionales:

1. **Acoplamiento al hostname del container.** El fetch SSR necesitaba
   `PUBLIC_API_URL` como URL absoluta al nginx sidecar del container.
   En dev/prod con Compose el hostname cambia entre environments y la
   URL se inlinea en build time — el SSR rompía cuando dev usaba un
   host y prod otro.
2. **Mismatch `PUBLIC_API_URL` entre build time y render runtime.**
   Astro SSR inlinea envs del `.env` del host en el bundle baked en la
   imagen del container. Cuando el `.env` del host cambiaba (o no
   llegaba al bundle), el render fallaba en silencio sin error visible.

El cambio a CSR island (commit `ee5639d1`) elimina ambos: el cliente
hace fetch relativo al mismo dominio, sin dependencia del hostname del
container ni del env baked.

## Consecuencias

- El **HTML inicial no incluye productos**. El cliente necesita JS
  activo para ver el catálogo — aceptable para este caso (SEO apunta a
  detalle de producto, no al listado agregado).
- El cliente debe tener **fetch al API disponible** y manejo correcto
  de **sesión / XSRF**. El fix `3357d4c7` ("no llamar `getXsrfToken`
  en GET") es requerido para que el GET inicial no rompa el SSR
  anterior ni la carga inicial del island.
- Estados de UI obligatorios según
  [`frontend/AGENTS.md`](../../../frontend/AGENTS.md) §11: loading,
  empty, error, success. El listado tarda más en mostrar la primera
  fila que con SSR.

## Cuándo revisarla

- Si aparece un **problema de SEO en el listado** (indexación de
  páginas agregadas tipo `/es/products`) que justifique mover render
  al server.
- Si la **resolución por dominio deja de depender del hostname** del
  container (ej. API y storefront servidos desde el mismo host).
- Si se introduce un CDN/edge cache para el storefront que requiera
  HTML estático por tenant.

## Referencias

- `a8e478d5` — feat(frontend): listar productos reales via API en La Voz
- `ee5639d1` — fix(frontend): reemplazar SSR fetch de products por CSR island
- `3357d4c7` — fix(frontend): no llamar getXsrfToken en GET (rompía SSR)
