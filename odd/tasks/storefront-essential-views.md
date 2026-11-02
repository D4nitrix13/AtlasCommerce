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
- [x] T2. `CartView.tsx` (island) + wiring de `cart.astro`: que el carrito
      lea el store y muestre lo acumulado, con empty state y CTA.
      Ruta: `delegated` (2+ archivos no triviales).
- [x] T3. Add-to-cart en `PandaProductDetail.astro` y `ProductCardCatalog.astro`
      (botón/selector de cantidad + acabado). Ruta: `delegated`.
      Isla `AddToCart.tsx` con modo `full` (PDP) y `compact` (card).
      Commit `9aff7d82`.
- [x] T4. `checkout.astro` lee el mismo store en su resumen (mismos ítems
      que el carrito). Ruta: `delegated` (writer acotado con T3).
      Islas `CheckoutOrderSummary` + `CheckoutReviewItems`. Commit `444ab3bc`.
- [x] T5. Filtros del PLP que recortan: `lib/catalog-filters.ts` (puro,
      testeable) + `products/index.astro` lee query params + `FiltersSidebar`
      como links/form GET. Tenant-aware: Panda y Kitsune sin marcas ni
      tallas. Test RED primero. Ruta: `delegated`. Commit `4f141919`.
- [x] T6. Montar `CheckoutSuccess` en checkout (`?step=success` en
      `VALID_STEPS`). Ruta: `inline` (edición mecánica de 1 archivo).
      `success` no es step del stepper: es la vista terminal. El redirect
      por carrito vacío ya no aplica sobre la confirmación.
- [x] T7. RFQ (`rfq/index.astro`) y bulk-order tenant-aware: catálogo
      Panda en Panda, `data/products.ts` en Kitsune/otros. Ruta: `inline`
      (2 ediciones mecánicas ya entendidas).
- [x] T8. Verificación de host/Caddy: `http://panda-estampados.com` con
      `/etc/hosts` + Caddy del host. **Verificado OK** — la sesión previa
      lo había dejado pendiente, pero Caddy ya estaba activo con el
      Caddyfile del repo y `/etc/hosts` ya tenía las entradas de Panda y
      Kitsune. Sin `sudo` disponible en esta sesión; no hizo falta.

## Criterios de aceptación

1. ✅ En el PLP, elegir categoría/color/precio **recorta** la grilla (y los
   counts/URL lo reflejan). En Panda y Kitsune no aparecen marcas
   Fender/Gibson ni tallas CH–XG. — Verificado en vivo:
   `?category=textil` deja 3 de 8 piezas; `?color=negro` deja 6;
   `?maxPrice=15` deja 4; `kitsune.com/es/products` → 0 apariciones de
   Fender/Gibson/tallas.
2. ✅ Agregar productos desde la PDP hace que el carrito muestre esos ítems
   (persistido tras recargar). El checkout muestra exactamente los
   mismos ítems y totales. — `AddToCart` montado en PDP y card; cart y
   checkout leen el mismo `stores/cart.ts` (28 tests unitarios verificados).
3. ✅ `?step=success` del checkout renderiza `CheckoutSuccess` (ticket).
   — Verificado en vivo: `checkout-success-title` + "Pedido confirmado".
4. ✅ RFQ y volumen muestran las 8 piezas en Panda y las figuras en Kitsune.
   — `isPandaStore(tenantSlug) ? PANDA_PRODUCTS : PRODUCTS`.
5. ✅ `bun run test`, `bun run typecheck` y `bun run lint` pasan (o solo con
   fallas preexistentes documentadas). — typecheck 0 errors; lint 64
   errors preexistentes (0 propios); suite completa 401/402 con 1 falla
   preexistente documentada.
6. ✅ Un work-unit commit por tarea (Conventional Commit), en rama feature.
   — 8 commits en `feat/storefront-essential-views`.

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
  (18 failed) → GREEN (18 passed). Commit `126d4c18`.
- Fix de T1 (2026-11-02): hidratación sin mismatch + constante sin usar.
  Commit `8168bbf9`.
- T2 (2026-11-02): `CartView.tsx` + wiring de `cart.astro`. TDD: RED →
  GREEN (8 tests). Commit `be42710b`. Ruta: `delegated` (writer acotado).
- T6 (2026-11-02): confirmación de pedido montada en checkout. Commit
  `81eb10a0`. Ruta: `inline`.
- T7 (2026-11-02): RFQ y volumen tenant-aware. Commit `f348d687`.
  Ruta: `inline`.
- T8 (2026-11-02): host verificado. `curl http://panda-estampados.com/`
  → 302, `/es/products` → 200, `/es/cart` → 200; `curl http://kitsune.com/`
  → 302, `/es/products` → 200. `caddy.service` active, puerto 80
  escuchando, `/etc/hosts` con las 6 entradas (3 Panda + 3 Kitsune).
  La configuración ya estaba hecha por Daniel entre sesiones.
- T3 (2026-11-02): `AddToCart.tsx` + montajes en PDP y card. TDD: RED
  (módulo inexistente) → GREEN (8/8). Commit `9aff7d82`. Ruta: delegated.
- T4 (2026-11-02): islas de checkout leen el store. TDD: RED (2 suites)
  → GREEN (7/7). Commit `444ab3bc`. Ruta: delegated (mismo writer que T3).
- **Bug encontrado y corregido en el spot check del parent** (previo al
  commit T4): `CheckoutOrderSummary` se montaba **sin `client:*`**, así
  que SSR-renderizaba el estado vacío y nunca hidrataba. Fix: `client:visible`
  agregado antes del commit. Verificado en vivo: ambas islas
  (`CheckoutOrderSummary.tsx`, `CheckoutReviewItems.tsx`) aparecen en el
  HTML de `?step=review`.
- T5 (2026-11-02): filtros del PLP por query params SSR. TDD: RED
  (módulo inexistente) → GREEN (18/18). Commit `4f141919`. Ruta: delegated.

## Evidencia

- `bun run test -- tests/unit/stores/cart.test.ts`: 18 tests, 18 passed (0.76s)
  — RED previo: 18 failed (`clearCart is not a function`).
- `bun run test -- tests/unit/components/storefront/cart/CartView.test.tsx`:
  8 tests, 8 passed (284ms) — RED previo: suite sin resolver.
- Spot check del parent tras el fix de hidratación:
  `bun run test -- tests/unit/stores/cart.test.ts tests/unit/components/storefront/cart/CartView.test.tsx`:
  26 tests, 26 passed (1.24s).
- `bun run typecheck` (writer, T2): 0 errors, 0 warnings, 41 hints
  preexistentes (ninguno en archivos nuevos).
- `bun run lint` (writer, T2): exit 1 — 259 problemas **preexistentes**
  (65 errors de base del repo). Los archivos de esta feature: 0 errors,
  4 warnings `max-lines-per-function` (misma clase que `CouponInput.tsx`).
  No es gate limpio del repo; no se corrigió lo preexistente.
- `bun run test -- tests/unit/components/storefront/cart/AddToCart.test.tsx`:
  8 passed. TDD: RED (módulo inexistente) → GREEN (8/8).
- `bun run test -- tests/unit/components/storefront/checkout/`: 7 passed
  (2 files). TDD: RED (2 suites) → GREEN (7/7).
- Spot check del parent (cart + checkout juntos):
  `bun run test -- tests/unit/components/storefront/cart/ tests/unit/components/storefront/checkout/`:
  28 tests, 28 passed (1.82s) — 5 archivos.
- `bun run typecheck` (post-fix de la isla): 0 errors, 0 warnings,
  40 hints (532 files).
- `bun run lint` (writer, T3+T4): 263 problems (64 errors preexistentes,
  199 warnings). Archivos de la feature: 0 errors, 5 warnings.
- Verificación en vivo (curl contra el dev server por Caddy):
  `/es/products/polo-pique` → `AddToCart` + `data-panda-finish` presentes;
  `/es/checkout?step=review` → `CheckoutOrderSummary.tsx` + `CheckoutReviewItems.tsx`
  como islas; `/es/cart` → `CartView` como isla; `/es/checkout?step=success`
  → `checkout-success-title` + "Pedido confirmado".
- `bun run test -- tests/unit/lib/catalog-filters.test.ts`: 18 passed.
  TDD: RED (módulo inexistente) → GREEN (18/18).
- **Suite completa al cierre** (una vez por sesión):
  `bun run test`: **401 passed, 1 failed (402)**, 43 archivos, 9.65s.
- **La falla es preexistente**, no de esta feature: `tests/unit/lib/product-card-swap.test.ts:437`
  (`buildStackedImages` esperaba `['front.webp','back.webp']`). Ni
  `frontend/src/lib/product-card-swap.ts` ni su test cambiaron respecto de
  `master` (`git diff master..HEAD` vacío sobre ambos) — falla ligada al
  pipeline `is_hover`/E1.5 que sigue en pausa.
- `bun run typecheck` (post-T5): 0 errors, 0 warnings, 40 hints (534 files).
- `bun run lint` (writer, T5): 266 problems (64 errors preexistentes,
  202 warnings). Archivos de T5: 0 errors, 3 warnings.
- Verificación en vivo de T5 (curl): sin filtro → 8 piezas;
  `?category=textil` → 3 (polo, playera, hoodie); `?color=negro` → 6;
  `?maxPrice=15` → 4 (bolígrafo, libreta, taza, tote);
  `kitsune.com/es/products` → 0 marcas Fender/Gibson ni tallas.
- Nota de entorno: `bun` no está en el PATH del shell; se invoca como
  `~/.bun/bin/bun`. El PATH debería incluir `~/.bun/bin` (regla
  `tooling/package-manager.md`).
- **RDD inoperable en este runtime.** `gentle-ai review assess --agent
  mimocode` responde `unassessable` + `review_due: true (high_risk)`.
  Motivo: "the active runtime is not eligible for immutable receipt
  review; supported immutable review runtimes: claude-code, opencode,
  codex". El switch `gentle-ai review mode` está **on** (default) y no
  se toca: es del usuario. Verificación de esta sesión = self-verification
  del writer + spot check del parent; el review nativo queda pendiente
  de correrse desde un runtime elegible (o el usuario desactiva RDD con
  `gentle-ai review mode disable --scope clone`).

## Próximo paso

Las 8 tareas están cerradas. La rama `feat/storefront-essential-views`
tiene 8 work-unit commits y está lista para revisión/merge (decisión del
usuario). Fuera de esta feature y pendiente como continuación natural:

- E1.5 (`is_hover`) sigue en pausa — incluye el test preexistente que
  falla (`product-card-swap.test.ts`).
- `SortDropdown` refleja `?sort=` pero no reordena (fuera de scope T5).
- Facetas con counts exactos pendientes de endpoint del backend.
- `CheckoutSummaryV3.astro` / `CheckoutReviewStepV3.astro` quedaron sin
  montar (reemplazados por las islas).
- Review RDD nativo: correrlo desde un runtime elegible (claude-code /
  opencode / codex) o desactivar el switch para este clone.
