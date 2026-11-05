# PDP — Layout, compra, tabs y recomendaciones

> **Status**: en ejecución.
> **Alcance**: vista 05 (Detalle de producto) del plan `atlascommerce-views-plan.md`.
> **Branch**: `feat/views/pdp-purchase-recommendations` (desde `master`).
> **Origen**: pedido directo del usuario 2026-11-05 (3 screenshots de referencia).
> **Mirror Engram**: pendiente (Engram MCP no conectado en esta sesión).

## Objective

Completar la PDP del storefront: layout sin espacio muerto, caja de compra
con cantidad validada por stock + "Comprar ya", tabs de información, y las
3 secciones de recomendación (También Te Puede Gustar, Frecuentemente
Comprados Juntos, Visto recientemente persistido en BD).

## Problem / Why

1. La galería desperdicia ancho: slides `aspect-[22/10]` con `object-contain`
   pillarboxean fotos cuadradas/verticales dentro de una columna 1.8fr.
2. El aside de información está tapado en `minmax(0,420px)` — el usuario pidió
   más ancho.
3. No hay caja de compra con cantidad ni "Comprar ya"; no hay tabs de
   información (hoy todo va apilado: descripción, specs, FAQ, reviews, envíos).
4. `ProductFrequentlyBoughtTogether` existe pero está comentado;
   `ProductRecentlyViewed` persiste en localStorage (se pierde entre
   dispositivos y no se renderiza en SSR); "También te puede gustar" no existe
   como sección con datos curados.
5. Regla vigente MEMORY.md: **las vistas deben ocupar TODO el ancho disponible**
   — `PandaProductDetail` (`max-w-6xl`) y cualquier `max-w` estrecho la violan.

## Decisions (plan pedidas por el usuario)

### D1 — "Visto recientemente": persistencia y retención

- **Tabla** `recently_viewed_products`: `(id, user_id NULL, guest_token NULL,
  product_id, viewed_at)` + UNIQUE `(user_id, product_id)` y UNIQUE
  `(guest_token, product_id)` parciales. Sin `tenant_id`: los tenants son
  instancias/BD separadas (migration-pattern.md).
- **Identidad**: usuario logueado → `user_id`; invitado → `guest_token`
  (UUID v1 año, cookie `rv_guest` propiedad del frontend Astro, se envía como
  header/body a la API).
- **Retención: ventana deslizante permanente — NO se reinicia.** Tope de
  **20 productos únicos** por identidad; re-ver un producto lo mueve al frente
  y los que sobran se expulsan del final. No hay reset periódico (un usuario
  que vuelve a la semana ve su lista real). Limpieza programada opcional
  (filas `viewed_at` > 180 días de guests) queda como follow-up, no bloquea.
- **Lectura SSR** desde BD (reemplaza el hidden-until-hydrate de localStorage).
  Se conserva localStorage solo como fallback si la API falla.
- **Escritura**: `POST /storefront/recently-viewed` desde el SSR del PDP
  (con Cookie/Origin reenviados, contrato de sesión ya documentado).

### D2 — "Comprar ya" → checkout

- Agrega la variante seleccionada + cantidad al cart store (`stores/cart.ts`)
  y navega a `/{locale}/checkout?step=shipping`. El checkout ya redirige a
  `/cart` si el carrito quedó vacío (flujo existente).
- "Añadir al carrito" mantiene el comportamiento actual (feedback inline).

### D3 — Tabs de información

`Descripción | Ficha técnica | Reseñas (N) | Preguntas frecuentes (N) | Envíos`
(accesibles: `role=tablist`, flechas, `aria-selected`; contenido reutiliza
`ProductDetailsBlock`, specs EAV agrupadas, `ProductReviews`, `ProductFAQ`,
panel de envíos). Los conteos van solo donde existen (Reseñas, Preguntas).

### D4 — Recomendaciones y datos

- **"También Te Puede Gustar"**: `ProductRelated` (reutilizado) con heading
  nuevo; datos = relaciones curadas `you_may_also_like` del producto, fallback
  a productos activos de la misma categoría, fallback a destacados del tenant.
  Basado en categoría/producto visto (no en search todavía — follow-up).
- **"Frecuentemente Comprados Juntos"**: relaciones `bought_together` curadas
  por seeder (catálogo chico: Panda ~6 productos, Kitsune ~7). Tabla
  `product_relations (product_id, related_product_id, type, position)` +
  UNIQUE `(product_id, related_product_id, type)`. Se reutiliza
  `ProductFrequentlyBoughtTogether` (hoy comentado) apilado con datos reales
  y "Agregar todo" que echa las líneas al cart store.
- **Seeder** `ProductRelationsSeeder` (idempotente por SKU/slug): bundles FBT
  por categoría + pares `you_may_also_like`. Sin ML ni co-occurrence de órdenes
  todavía (catálogo insuficiente).

## Scope autorizado

- `frontend/src/**` (PDP, componentes de producto/carrito, i18n es/en).
- `backend/database/migrations/**`, `backend/app/Models/**`,
  `backend/app/Http/Controllers/Api/Storefront/**`, `backend/routes/**`,
  `backend/database/seeders/**`, `backend/tests/**`.
- NO tocar: admin, image pipeline, auth/2FA, `feat/e16-reupload-idempotency`.

## Constraints

- `bun` exclusivo en frontend; Docker por `scripts/docker-stack.sh`.
- Migraciones sin guards `Schema::has*` (migration-pattern.md).
- Componentes ≤250 LOC / páginas ≤400 (frontend/AGENTS.md); la PDP de 1337
  líneas ya excede — este trabajo NO la agranda, el contenido se mueve a
  componentes nuevos.
- i18n: todas las strings nuevos en `i18n/locales/{es,en}.ts` + `types.ts`.
- Storybook stories para componentes visuales nuevos (convención vistas).
- Commits convencionales en español, prosa plana; **un work-unit commit por
  tarea** en el branch de feature. Push/PR/merge = decisión del usuario.
- TDD estricto **ON** (fuente: directiva de sesión `gentle-ai:strict-tdd-mode`).
  Backend runner: PHPUnit (`phpunit.xml`, vía container backend).
  Frontend runner: `bun run test` (unit) + `bun run astro check` + lint.
  Para UI nueva sin lógica testeable: checks funcionales (build + check).

## Delivery strategy

`single-pr` — un branch de feature con work-unit commits; el PR (y su posible
sliceo, gate ~400 líneas) es decisión del usuario al cierre. Forecast de
autoredactadas (additions+deletions): **~2.200** líneas (T1 60 · T2 350 · T3
400 · T4 500 · T5 450 · T6 350 · T7 90). Excede el heurístico de 400 por
tarea-solo-visual: se documenta y se sigue (no partir artificialmente).

## Tasks

- [ ] **T1** Layout PDP full-width: slide de galería sin pillarbox, grid
      con aside más ancho, `PandaProductDetail` sin `max-w-6xl`.
      _Route: inline (ediciones mecánicas ya decididas, 3 archivos)._
- [ ] **T2** Caja de compra: cantidad 1..stock de la variante (validada),
      "Añadir al carrito" + "Comprar ya" → checkout. i18n + wire en PDP.
      _Route: delegated writer._
- [ ] **T3** Tabs `Descripción | Ficha técnica | Reseñas | Preguntas | Envíos`
      con conteos, reutilizando los bloques existentes. i18n + wire en PDP.
      _Route: delegated writer._
- [ ] **T4** Backend `product_relations`: migración, modelo, endpoint
      storefront, `ProductRelationsSeeder` (FBT + you_may_also_like). TDD.
      _Route: delegated writer._
- [ ] **T5** Backend `recently_viewed_products`: migración, modelo, endpoints
      POST/GET con cap 20 + move-to-front, identidad user/guest. TDD.
      _Route: delegated writer._
- [ ] **T6** Frontend secciones: FBT con datos reales + "Agregar todo",
      "También Te Puede Gustar" con fallbacks, "Visto recientemente" SSR/BD.
      _Route: delegated writer._
- [ ] **T7** Cierre: checks completos (build, astro check, tests, lint vs
      HEAD), verificación visual desktop/mobile, resumen.

## Acceptance criteria

1. Sin espacios muertos horizontales en galería; aside de info visiblemente
   más ancho; ningún `max-w` estrecho en la PDP ni PandaProductDetail.
2. Cantidad nunca excede stock de la variante seleccionada (ni por UI ni por
   el store); stock 0 → compra deshabilitada.
3. "Comprar ya" deja la línea en el carrito y aterriza en checkout.
4. Tabs navegables por teclado con conteos correctos de reseñas/FAQ.
5. Las 3 secciones de recomendación renderizan con datos reales de BD/seeders
   y se ocultan limpiamente cuando no hay datos.
6. "Visto recientemente" persiste en BD por usuario/guest, sobrevive
   navegadores/dispositivos (logueado), cap 20 sin reset periódico.
7. Tests backend verdes (RED→GREEN documentado); frontend `astro check` +
   unit tests sin errores nuevos; lint sin errores propios nuevos vs HEAD.

## Applicable checks

- Backend: `php artisan test --filter=<Suite>` (container) → suite completa al cierre.
- Frontend: `bun run test`, `bun run astro check`, `bun run lint` (vs HEAD),
  `bun run build`.
- Visual: playwright-cli (firefox) desktop 1440 + mobile 390 sobre
  `panda-estampados.com` (Caddy :80 o Host header contra :5321).

## Progress

- 2026-11-05 — Feature doc creado. Decisiones D1-D4 registradas.

## Next step

T1 (layout full-width) → commit → T2.
