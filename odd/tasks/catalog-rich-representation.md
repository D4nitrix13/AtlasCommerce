# Representación rica de productos — replicar La Voz en las 3 tiendas

Fecha: 2026-11-02. Continuación de `storefront-essential-views.md`
(rama `feat/storefront-essential-views` ya tiene 12 commits).

## Objetivo

Que la vista de productos de **Panda Estampados**, **Kitsune** e
**Instrumentos Musicales La Voz** use **la misma representación**: la
que hoy solo tiene La Voz. Misma estructura, mismo render, solo cambia
el catálogo de cada tienda.

## Problema

La página del catálogo (`products/index.astro`) es **una sola,
compartida**. Lo que hace que La Voz se vea como se ve son los datos y
qué campos dibuja la card:

| Campo | La Voz | Panda | Kitsune |
|---|---|---|---|
| Imágenes | webp reales por variante (front **+ back**) | SVG generado, sin back | placeholder, sin galería |
| Hover / swap | ✅ (`isHover`, `srcset`) | ❌ | ❌ |
| Swatches de acabado | ✅ (`variants[].hex`) | ✅ | ❌ |
| Badges (Nuevo/Oferta/Último stock) | ✅ | ❌ | ✅ sin renderizar |
| Marca | ✅ | vacía | ❌ |
| Precio tachado | ✅ (`compareAtPriceCents`) | ❌ | ✅ sin renderizar |
| Stock / "Edición limitada" | ✅ (`isExclusive`) | fijo 40 | ❌ |
| Tallas | ✅ (`sizes`) | ❌ | ❌ |

Y hay dos cards: `ProductCard.astro` (home, dibuja badges/marca/stock/
limitado) y `ProductCardCatalog.astro` (catálogo, dibuja hover-swap +
swatches pero NO badges/marca/stock). El usuario pidió unificar todo a
la representación de La Voz en las tres tiendas.

## Datos que hay disponibles

- `assets/products/instrumentos-musicales-la-voz/**` → **10.249 webp**
  reales (front/back/headstock por variante), generados por
  `buildImageUrl` (`lib/product-mock-factory.ts`).
- `assets/products/{panda-estampados,kitsune}/` → carpetas **vacías**.
- No hay fotos de producto para Panda/Kitsune → las imágenes se
  **generan** al mismo molde (front + back por variante) para que el
  hover-swap y los swatches funcionen igual.

## Scope

Dentro:

1. **Shape unificado**: `types/catalog.Product` como única
   representación (es la que consume `ProductCardCatalog` y la que
   produce `mapBackendToProduct`).
2. **Factory rica compartida**: generar `Product` con imágenes
   front/back por variante (`isHover`, `variantId`, `srcset`,
   `sortOrder`), `variants` con `hex`/`finishSlug`/`finishLabel`/
   `condition`/`stock`, más `badges`, `compareAtPriceCents`, `brand`,
   `stock`, `isExclusive`, `sizes`.
3. Panda y Kitsune pasan a salir de esa factory.
4. La Voz mapea sus `LA_VOZ_PRODUCTS` (webp reales) al mismo shape.
5. **Card de catálogo completa**: `ProductCardCatalog` dibuja badge,
   marca, precio tachado, estado de stock y "Edición limitada".
6. **Filtros por tenant con datos reales** (categorías, marcas, colores,
   tallas con counts) para las 3 tiendas.
7. **PLP con fallback por tenant** para las 3 tiendas (hoy solo Panda).

Fuera de scope:

- mm-boutique y morales-store (la factory queda genérica, pero sus
  catálogos no se crean acá).
- Backend/API real: sigue siendo mock client-side de datos.
- E1.5 (`is_hover`) y el merge a master (siguen esperando).

## Restricciones

- Frontend usa **bun** exclusivamente (`~/.bun/bin/bun`, no está en PATH).
- Un comando pesado por bloque; tests filtrados en iteración.
- Conventional Commits, sin Co-Authored-By.
- Paridad visual con el diseño Bonfire existente.

## TDD

- **Modo:** activo (directiva `gentle-ai:strict-tdd-mode`).
- **Runner:** `~/.bun/bin/bun run test` (vitest) en `frontend/`.
- **Aplicación:** RED → GREEN para toda lógica pura (factory, mapping,
  filtros). Para el wiring Astro: `typecheck` + `lint` + los tests.

## Tareas

- [ ] T1. `lib/catalog-product-factory.ts` (puro): factory rica que
      genera `Product` completo (imágenes front/back por variante con
      `isHover`/`srcset`, variants con hex/finish/condition/stock,
      badges, compareAt, brand, stock, isExclusive, sizes). Test RED
      primero en `tests/unit/lib/catalog-product-factory.test.ts`.
      Ruta: `delegated`.
- [ ] T2. Panda al shape rico: `data/panda-catalog.ts` sale de la
      factory (8 piezas, cada acabado con front+back). Ruta: `delegated`
      (con T1 si el writer lo cubre).
- [ ] T3. Kitsune al shape rico: `data/products.ts` (12 figuras) gana
      variants/imágenes front+back/badges/compareAt/brand. Ruta:
      `delegated`.
- [ ] T4. La Voz al shape rico: mapear `LA_VOZ_PRODUCTS` a `Product`
      con `ProductImage[]` reales (front+back, `isHover`, `srcset`) para
      que el PLP lo pueda usar como fallback. Ruta: `delegated`.
- [ ] T5. `ProductCardCatalog.astro` dibuja badge, marca, precio
      tachado, stock/"Agotado" y "Edición limitada" (lo que hoy solo
      hace `ProductCard.astro`). Ruta: `delegated`.
- [ ] T6. Filtros por tenant con datos reales y counts (categorías,
      marcas, colores, tallas) para las 3 tiendas. Ruta: `delegated`.
- [ ] T7. PLP con fallback de catálogo para las 3 tiendas (no solo
      Panda). Ruta: `inline` (wiring ya entendido).
- [ ] T8. Verificación en vivo de las 3 tiendas (render + filtros +
      card rica) y suite completa al cierre.

## Criterios de aceptación

1. Las tres tiendas renderizan cards con **imagen hover/back**,
   swatches, badge, marca, precio tachado y estado de stock.
2. Los filtros muestran categorías/marcas/colores/tallas **por tenant**
   con counts reales (no placeholders en 0).
3. Sin backend, las tres tiendas muestran catálogo (fallback por tenant).
4. `bun run test` / `typecheck` / `lint` pasan (solo fallas
   preexistentes documentadas).
5. Un work-unit commit por tarea, Conventional Commit.

## Cheques aplicables

- `bun run test` (filtrado durante iteración; suite completa al cierre)
- `bun run typecheck`
- `bun run lint`
- Verificación visual con Playwright (Firefox) sobre las 3 tiendas.

## Progreso

(nada aún)

## Evidencia

(nada aún)

## Próximo paso

T1 — `lib/catalog-product-factory.ts` con test RED primero.
