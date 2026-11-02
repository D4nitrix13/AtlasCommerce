# Storefront esencial — Panda Estampados y Kitsune

Fecha: 2026-11-02. Continuación de `prototype-panda-next-session.md`.
El working tree está limpio sobre `master`. E1.5 (`is_hover`) sigue en
pausa: no mezclar.

## Objetivo

Que el storefront de **Panda Estampados** y **Kitsune** tenga las vistas
esenciales de un e-commerce funcionando de verdad sobre sus catálogos:
filtros que recortan la grilla, carrito que acumula, checkout con los
mismos ítems, confirmación de pedido visible, y B2B (RFQ / volumen)
que respete el tenant.

## Problema

El prototipo de Panda quedó "presentable pero con cables sueltos":

- Los filtros del PLP se renderizan pero **no recortan la grilla**
  (estado hardcodeado a `null`, sin handler).
- El carrito y el checkout leen `PANDA_DEMO_LINES` / `PRODUCTS` fijo —
  `stores/cart.ts` está **vacío (0 bytes)**. Nada acumula.
- `CheckoutSuccess.astro` existe pero está **huérfano**: `?step=success`
  cae de vuelta al step `shipping`.
- RFQ y bulk-order hardcodean `PANDA_PRODUCTS` aunque el host sea Kitsune.
- Kitsune no tiene módulo de catálogo propio: usa `data/products.ts`
  (figuras anime), que es correcto para su nicho, pero los filtros del
  PLP le muestran marcas Fender/Gibson y tallas CH–XG (de La Voz).

## Por qué

El usuario pidió "hacer todas las tareas" pendientes de la sesión previa
y "todas las vistas esenciales de Panda Estampados y Kitsune". Sin
carrito real ni filtros que corten, las vistas existen pero no sirven
como e-commerce.

## Scope

Dentro:

1. `stores/cart.ts` — carrito real (localStorage + `useSyncExternalStore`).
2. Filtros del PLP que filtran (categoría, color, precio) — SSR por query
   params, tenant-aware (sin marcas/tallas en Panda y Kitsune).
3. Montar `CheckoutSuccess` en el flujo de checkout.
4. RFQ y bulk-order tenant-aware (Panda → 8 piezas; Kitsune → catálogo
   de figuras).
5. Add-to-cart en PDP y card, para que el carrito tenga de dónde acumular.
6. Tests (vitest) de la lógica pura: cart store, filter logic.
7. Verificación de Caddy/host (`http://panda-estampados.com`) — solo si
   `sudo` sin contraseña está disponible; si no, documentar para Daniel.

Fuera de scope (explícito):

- Admin, búsqueda, wishlist, 2FA, HTTPS con CA local.
- E1.5 (`is_hover`) — sigue en pausa.
- Backend/API real de carrito: el prototipo es client-side (localStorage).
- Rediseño de cuenta/pedidos.

## Restricciones y convenciones

- Frontend usa **bun** exclusivamente (`.ai/rules/tooling/package-manager.md`).
- Containers por `scripts/docker-stack.sh`, nunca `docker compose` directo.
- Un comando pesado por bloque; tests filtrados durante iteración
  (`.ai/rules/tooling/resource-budget.md`).
- Conventional Commits, sin Co-Authored-By.
- Patrón de stores: React 19 + `useSyncExternalStore`, sin dependencias
  externas (ver `stores/auth.ts`). `stores/cart.ts` sigue ese patrón.

## TDD

- **Modo:** activo (directiva `gentle-ai:strict-tdd-mode` de sesión).
- **Runner:** `bun run test` (vitest) en `frontend/`.
- **Aplicación:** RED → GREEN → REFACTOR para toda lógica pura (cart
  store, filter logic). Para el wiring de páginas Astro, la verificación
  es `bun run typecheck` + `bun run lint` + los tests de la lógica; las
  páginas SSR no se testean con vitest en este repo.

## Tareas

- [x] T1. `stores/cart.ts`: store de carrito con persistencia localStorage
      (add, remove, updateQty, clear, totales con envío gratis > $50 e
      impuesto). Test RED primero (`tests/unit/stores/cart.test.ts`).
      Ruta: `inline` (dos archivos ya entendidos, sin investigación).
- [ ] T2. `CartView.tsx` (island) + wiring de `cart.astro`: que el carrito
      lea el store y muestre lo acumulado, con empty state y CTA.
      Ruta: `delegated` (2+ archivos no triviales).
- [ ] T3. Add-to-cart en `PandaProductDetail.astro` y `ProductCardCatalog.astro`
      (botón/selector de cantidad + acabado). Ruta: `delegated`.
- [ ] T4. `checkout.astro` lee el mismo store en su resumen (mismos ítems
      que el carrito). Ruta: `delegated` (junto con T2 si el writer lo
      cubre; si no, separado).
- [ ] T5. Filtros del PLP que recortan: `lib/catalog-filters.ts` (puro,
      testeable) + `products/index.astro` lee query params + `FiltersSidebar`
      como links/form GET. Tenant-aware: Panda y Kitsune sin marcas ni
      tallas. Test RED primero. Ruta: `delegated`.
- [ ] T6. Montar `CheckoutSuccess` en checkout (`?step=success` en
      `VALID_STEPS`). Ruta: `inline` (edición mecánica de 1 archivo).
- [ ] T7. RFQ (`rfq/index.astro`) y bulk-order tenant-aware: catálogo
      Panda en Panda, `data/products.ts` en Kitsune/otros. Ruta: `inline`
      (2 ediciones mecánicas ya entendidas).
- [ ] T8. Verificación de host/Caddy: `http://panda-estampados.com` con
      `/etc/hosts` + Caddy del host. Si `sudo` pide contraseña, dejar los
      comandos documentados para Daniel y cerrar la tarea como bloqueada
      en el entorno, no como código.

## Criterios de aceptación

1. En el PLP, elegir categoría/color/precio **recorta** la grilla (y los
   counts/URL lo reflejan). En Panda y Kitsune no aparecen marcas
   Fender/Gibson ni tallas CH–XG.
2. Agregar productos desde la PDP hace que el carrito muestre esos ítems
   (persistido tras recargar). El checkout muestra exactamente los
   mismos ítems y totales.
3. `?step=success` del checkout renderiza `CheckoutSuccess` (ticket).
4. RFQ y volumen muestran las 8 piezas en Panda y las figuras en Kitsune.
5. `bun run test`, `bun run typecheck` y `bun run lint` pasan (o solo con
   fallas preexistentes documentadas).
6. Un work-unit commit por tarea (Conventional Commit), en rama feature.

## Cheques aplicables

- `bun run test` (vitest, filtrado por archivo durante iteración)
- `bun run typecheck`
- `bun run lint`
- Suite completa solo al cerrar la feature, una vez.

## Progreso

- [x] Doc de feature creado. Rama `feat/storefront-essential-views`.
- Mirror Engram `odd/storefront-essential-views/tasks`: **pendiente** —
  el servidor Engram no está conectado en esta sesión. Resincronizar
  cuando esté disponible.
- T1 (2026-11-02): store de carrito + 18 tests. TDD: RED observado
  (18 failed) → GREEN (18 passed).

## Evidencia

- `bun run test -- tests/unit/stores/cart.test.ts`: 18 tests, 18 passed (0.76s)
  — RED previo: 18 failed (`clearCart is not a function`).
- Nota de entorno: `bun` no está en el PATH del shell; se invoca como
  `~/.bun/bin/bun`. El PATH debería incluir `~/.bun/bin` (regla
  `tooling/package-manager.md`).

## Próximo paso

T2 — `CartView.tsx` + wiring de `cart.astro` al store.
