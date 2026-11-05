# Sesión 1 — Home / portada + composición del header

> **Worktree**: `~/Code/AtlasCommerce-worktrees/home` · **Rama**: `feat/views/home`
> **Base**: `feat/storefront-footer-legal` (header/footer/layout es territorio de
> Sesión 1 según [`vistas-cliente-sessions.md`](vistas-cliente-sessions.md)).
> **Brief**: [`vistas-cliente-sessions.md`](vistas-cliente-sessions.md) §Sesión 1.
> **Estado**: en progreso.
> **TDD**: estricto (config de sesión). UI → `astro check` + lint + verificación
> visual real; lógica pura → tests primero.
> **Engram mirror** (`odd/home-portada/tasks`): pendiente (Engram no disponible).

## Objetivo

Componer el header del storefront y luego la portada del tenant
(`/{locale}/`), responsive y multi-tenant (Panda teal / Kitsune violeta / …),
con destacados reales desde API y sin mocks.

## Problema

El header ya cumple la spec Nike documentada en
[`product-views-images-header.md`](product-views-images-header.md) §F3, pero
tiene tres defectos concretos verificados en captura (1440):

1. **El logo sale vacío**: `brand-assets/` no tiene ningún asset y el endpoint
   `/api/v1/storefront/assets/tenants/{slug}/branding/logo.webp` responde 404.
   El header deja un cuadrado tintado sin marca.
2. **Nav débil**: "Buscar" duplica el search pill; "Casos" apunta a `/search`
   (placeholder) cuando el home tiene una sección real de casos de uso.
3. **Search pill muy ancho** (`w-40 xl:w-64`) — se come el centro de la fila.

## Alcance autorizado (confirmado por el usuario 2026-11-05)

- Fallback de marca con iniciales en un tile diseñado (sin depender del asset).
- Nav = Inicio · Productos · Casos de uso.
- Search pill más compacta.
- Fila balanceada (nav centrada de verdad).
- Luego la portada Home completa (tareas T2+).

## Fuera de scope

- Subir/crear los assets de logo (gap de contenido: `brand-assets/` está vacío).
- Vistas Catálogo / PDP / Carrito / Cuenta (otras sesiones).
- Backend (los endpoints necesarios ya existen).

## Restricciones

- Frontend `~/.bun/bin/bun` exclusivamente. Nunca npm/pnpm.
- Containers solo por `scripts/docker-stack.sh`. Nunca `docker compose` directo,
  nunca `bun astro dev` ad-hoc.
- Convención de links SSR sin islas (`workflow/decisions-storefront-rendering.md`).
- i18n es/en para todo string nuevo. TypeScript estricto.
- No pushear ni mergear sin que el usuario lo pida.

## Infraestructura de esta sesión (hallazgos)

- **Bind mounts dejan de propagar archivos/directorios nuevos** en este entorno:
  hay que recrear el container (`down` + `up`), no alcanza `restart`. Afectó
  tanto al frontend como al backend.
- El dev server en `:5321` monta el **checkout principal**
  (`~/Code/AtlasCommerce/frontend`), no el worktree. Para verificación visual
  del código del worktree hay que redirigir el mount temporalmente y restaurarlo.
- `brand-assets/` está vacío: ningún tenant tiene logo. Gap de contenido.

## Tareas

### T1 — Composición del header
- [ ] `StorefrontHeaderBrand.astro`: fallback de marca con iniciales en un tile
      con el accent del tenant. El `<img>` del logo (cuando exista el asset)
      debe cubrir el fallback; si falla, quedan las iniciales — nunca un cuadrado vacío.
- [ ] `StorefrontHeader.astro` / `StorefrontHeaderNav.astro`: nav = Inicio ·
      Productos · Casos de uso. "Casos de uso" ancla a la sección de casos del
      home (`/{locale}#<id-de-seccion>`). Sin "Buscar" (duplica el pill).
- [ ] `StorefrontHeaderActions.astro`: search pill más compacta; fila balanceada
      (nav centrada, brand a la izquierda, acciones a la derecha con el mismo ancho).
- [ ] i18n es/en de los labels nuevos/renombrados.
- [ ] Verificación: `bun run test` · `astro check` · `build` · `eslint` ·
      captura visual 1440/390.

**Aceptación**: una sola fila sin wrap, nav centrada, marca visible sin asset,
search pill compacta, hit areas ≥40px, hover/focus correctos, 0 errores nuevos.

### T2 — Portada Home: inventario vs mockup _(pendiente)_
### T3 — Portada Home: secciones y destacados reales _(pendiente)_
### T4 — Cierre de sesión (checks + capturas + reporte) _(pendiente)_

## Criterios de aceptación (sesión completa)

- Portada responsive 1440/390, 0 errores nuevos, destacados reales (sin mocks),
  acentos por tenant correctos, header en una fila a todo el ancho.

## Evidencia de progreso

| Tarea | Estado | Commit | Verificación |
|---|---|---|---|
| T1 | hecha | (este) | `bun run test`: 525 OK · `astro check`: 0 errors · `build`: OK · `eslint`: exit 0 · capturas `output-playwright-home-header-{1440,390}.png` |
| T2 | pendiente | — | — |
| T3 | pendiente | — | — |
| T4 | pendiente | — | — |

### Notas T1

- Fallback de marca: tile con las iniciales del `shortName` (máx. 2, mayúsculas,
  color accent) y el `<img>` encima en absoluto. Si el asset no existe (es el
  caso: `brand-assets/` está vacío y el endpoint da 404) el `onerror` oculta la
  imagen y quedan las iniciales. Nunca un cuadrado vacío.
- Nav: Inicio · Productos · Casos de uso. Eliminado "Buscar" (duplicaba el
  search pill). `UseCases.astro` ahora expone `id="use-cases"` — **T2/T3 deben
  conservar ese id** o el link del header queda cojo. Clave
  `storefront.header.nav.search` eliminada de es/en/types.
- Fila balanceada: `md:grid md:grid-cols-[1fr_auto_1fr]` (brand | nav | acciones).
  En mobile mantiene `flex` (hamburguesa + brand + acciones esenciales).
- Search pill: `w-36 xl:w-48` (antes `w-40 xl:w-64`).
- **Bug corregido en el camino**: `iconLinkClasses` incluye `inline-flex`, que
  gana en el cascade de Tailwind sobre `hidden`, así que los iconos de cuenta y
  wishlist NUNCA se ocultaban en mobile pese a `hidden md:inline-flex`. Cambiado
  a `max-md:hidden` (variante, gana sobre la utility base). Verificado en
  captura 390: queda marca + hamburguesa + search + carrito, como documenta el
  docblock.
- Verificación visual hecha redirigiendo temporalmente el mount del frontend
  del dev server al worktree (editando `docker-compose.yml`, recreando con
  `docker-stack.sh`, y restaurando después). El compose quedó igual que antes.
  Hallazgo: el dev server monta el checkout principal, no el worktree — las
  sesiones en paralelo van a necesitar un mecanismo para esto.

## Próximo paso

T1 — composición del header.
