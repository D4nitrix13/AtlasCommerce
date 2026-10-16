# Plan: PDP — Bugs visuales + reusabilidad

> **Status**: pendiente aprobación. Branch de trabajo desde `master` (HEAD `098daa9a`).
>
> **Scope**: arreglar 4 bugs visuales reportados en la PDP + garantizar de reusabilidad transversal (algunos productos usan estos componentes, otros no — no deben romperse ni acoplarse a datos hardcoded).
>
> **Engram mirror**: `odd/pdp-q1-2026-bugs-reusability/tasks`

---

## 0. Contexto y motivación

Tras 6 commits del sprint PDP La Voz Instrumentos, el usuario probó la PDP y reportó:

1. Al **recargar en una sección** (ej. Reseñas) la página vuelve al top — no hay scroll restoration.
2. Al **abrir Miscelánea** (o cualquier `<details>` de specs) se ve un **flash visual**: primero colapsa, luego expande.
3. Al **estar en una imagen ≠ primera** del carousel y recargar, la galería recorre cada imagen hasta llegar a la guardada.
4. El **borde del thumbnail de Acabado activo** tiene un tinte verde-azulado (primary) que rompe la consistencia visual — debe ser neutral.
5. Hay **componentes no finales / duplicados** (`ProductDetailDescription` hace lo mismo que `ProductDetailsBlock`; `ProductDetailColorSwatches` está importado pero nunca se renderiza; script legacy `data-toggle-details` queda orphaned).
6. **Reusabilidad**: los componentes se usan en algunos productos y en otros no. No deben tener datos hardcoded de Fender ni fallar si no existen `variantAttributes`, `highlights`, `groupedSpecs`, `featuredArticle`, `financing`, etc.

La Voz es nicho instrumentos; Nike (otro tenant) usa otros componentes. El criterio de aceptación es: **PDP funciona sin que el mock provea NINGÚN campo opcional**.

---

## 1. Archivos a tocar

| Archivo | Acción |
|---|---|
| `frontend/src/pages/[locale]/products/[slug].astro` | Eliminar script legacy `data-toggle-details` (l. 541-548); eliminar import muerto `ProductDetailColorSwatches` (l. 34); ajustar selector Acabado (l. 372) a ring slate-900; eliminar `<ProductDetailDescription>` duplicado (l. 394); integrar scroll restoration |
| `frontend/src/components/storefront/products/ProductSpecificationsByCategory.astro` | Migrar de localStorage a cookie SSR (sin flash); eliminar script inline actual (l. 132-181) |
| `frontend/src/components/storefront/products/ProductDetailGallery.astro` | Cambiar `scrollIntoView` por `scrollLeft` directo + observer-skip inicial; sin cambios visuales |
| `frontend/src/components/storefront/products/ProductDetailDescription.astro` | **ELIMINAR** archivo + su `.stories.ts` (funcionalidad consolidada en `ProductDetailsBlock`) |
| `frontend/src/components/storefront/products/ProductDetailColorSwatches.astro` | **ELIMINAR** archivo + su `.stories.ts` (no se usa; el picker vive inline en `slug.astro`) |

Sin archivos nuevos. Sin dependencias nuevas.

---

## T1 — Migrar specs accordion a SSR con cookie (Bug "primero se acopla")

**Problema**: `ProductSpecificationsByCategory.astro` líneas 132-181 usa localStorage + script inline que aplica `open=""` DESPUÉS del primer paint → flash visible.

**Solución**: leer cookie `pdp-specs-expanded-<slug>` en SSR (`Astro.cookies.get()`) y renderizar `<details open>` si el spec-id está en el array. Al toggle, escribir cookie con `SameSite=Lax, Max-Age=31536000`.

**Cambios**:
- Frontmatter de `ProductSpecificationsByCategory.astro`: añadir prop `expanded: readonly string[]` (default `[]`) que el padre pasa desde cookie.
- Render del `<details>`: `open={expanded.includes(specId) ? '' : undefined}`.
- Script inline nuevo: en `toggle` event, escribir cookie + actualizar set en memoria (eliminar el bloque de lectura inicial — server ya pintó el estado correcto).
- `slug.astro`: leer cookie, parsear con `try/catch` (defensivo), pasar a `<ProductSpecificationsByCategory expanded={...} />`.

**Decisión**: cookie en vez de sessionStorage porque SSR solo lee cookies. localStorage no es accesible en server. Cookie es HTTP-only=false (necesario leer+escribir en cliente) + SameSite=Lax (no se filtra cross-origin) + Max-Age=1 año (persiste entre sesiones, equivalente UX al localStorage).

**Commit**: `fix(pdp): specs accordion SSR via cookie (elimina flash visual)`

**Verificación**:
- `cd frontend && bun run typecheck` → exit 0
- `cd frontend && bun run storybook` → abrir story de `ProductSpecificationsByCategory` con `expanded={['cuerpo']}` → primer paint con `<details>` abierto, sin toggle JS.
- Manual: dev server → PDP Fender → expandir "Miscelánea" → recargar → debe llegar ya abierto sin flash.

---

## T2 — Gallery: scrollLeft directo sin observer (Bug "recorre cada imagen")

**Problema**: `ProductDetailGallery.astro` líneas 188-242: `scrollIntoView({behavior:'auto', inline:'start'})` sobre un contenedor `snap-x snap-mandatory overflow-x-auto` causa reflow animado por cada slide intermedio. Además el IntersectionObserver se suscribe ANTES del scroll inicial, así que durante el scroll programático dispara callbacks y actualiza el thumb activo slide por slide.

**Solución**: usar `scroller.scrollLeft = target.offsetLeft` directo (sin pasar por scrollIntoView ni observer). Desconectar observer durante inicialización y reconectar después con `requestAnimationFrame`.

**Cambios**:
- `setupGallery()` (l. 166-244):
  - Reemplazar `slides[savedIndex]?.scrollIntoView({behavior:'auto', inline:'start', block:'nearest'})` por `if (slides[savedIndex]) scroller.scrollLeft = slides[savedIndex].offsetLeft`.
  - Llamar `observer.disconnect()` antes del primer set y reconectar con `requestAnimationFrame(() => slides.forEach(s => observer.observe(s)))`.

**Commit**: `fix(pdp): gallery scrollLeft directo (sin recorrido visual)`

**Verificación**:
- Manual: PDP Fender → click thumbnail #4 → recargar → debe aparecer en imagen #4 sin recorrer #1→#2→#3→#4.
- `cd frontend && bun run typecheck` → exit 0

---

## T3 — Quitar color del ring del thumbnail Acabado activo (Bug color)

**Problema**: `slug.astro` línea 372: `ring-[var(--primary,#475569)]` aplica tinte verde-azulado al thumbnail activo. El usuario marcó con círculo rojo apuntando al swatch "Halo Metallic" → quiere borde slate/neutral.

**Solución**: cambiar a `ring-slate-900` (neutral, consistente con el patrón Gibson que usa borde negro). El picker inline vive en `slug.astro` líneas 358-389, NO en `ProductDetailColorSwatches` (que está muerto — ver T5).

**Cambios**:
- `slug.astro` línea 372: `ring-[var(--primary,#475569)] ring-offset-1` → `ring-slate-900 ring-offset-2`.
- Mantener el `border-[var(--primary,#475569)]` (más sutil) o pasar a `border-slate-900` también para coherencia. Decisión: `border-slate-900` + `ring-slate-900 ring-offset-1` para consistencia.

**Commit**: `fix(pdp): thumbnail Acabado activo con ring neutral slate-900`

**Verificación**:
- Visual: screenshot del picker swatch con thumbnail activo debe verse negro/gris, sin tinte verde-azulado.
- `cd frontend && bun run typecheck` → exit 0

---

## T4 — Consolidar `ProductDetailDescription` → `ProductDetailsBlock` (Bug duplicación)

**Problema**: `ProductDetailDescription.astro` (76 líneas) y `ProductDetailsBlock.astro` (107 líneas) implementan el mismo patrón "descripción + Leer más/menos". `slug.astro` usa ambos: `ProductDetailDescription` en el aside (l. 394) y `ProductDetailsBlock` debajo (l. 455). Código duplicado = bugs duplicados = archivos no finales.

**Solución**: eliminar `ProductDetailDescription.astro` + su `.stories.ts`. Mover la versión "fila inline" del aside a una variante de `ProductDetailsBlock` con prop `variant: 'compact' | 'full'` (default `full`).

**Cambios**:
- `ProductDetailsBlock.astro`: añadir prop `variant?: 'inline' | 'full'` (default `full`). Si `variant='inline'`:
  - Sin card wrapper (no `rounded-2xl border bg-white p-6`).
  - Sin eyebrow/heading.
  - Truncado a 80 chars (en vez de 240).
  - Toggle "más"/"menos" inline.
- `slug.astro` línea 394: reemplazar `<ProductDetailDescription ...>` por `<ProductDetailsBlock variant="inline" intro={detail.description} ... />`.
- Eliminar archivos:
  - `frontend/src/components/storefront/products/ProductDetailDescription.astro`
  - `frontend/src/components/storefront/products/ProductDetailDescription.stories.ts`

**Commit**: `refactor(pdp): consolidar Description en ProductDetailsBlock (variant inline)`

**Verificación**:
- Storybook: `ProductDetailsBlock` debe tener variants `Default`, `Long`, `Short`, `Inline` con sus datos mockeados.
- Manual: PDP Fender → aside debe mostrar "Detalles" + descripción truncada + toggle "Leer más" (igual que antes).
- `cd frontend && bun run typecheck` → exit 0
- `cd frontend && bun run storybook:test` → exit 0

---

## T5 — Eliminar `ProductDetailColorSwatches` + script `data-toggle-details` (código muerto)

**Problema**:
- `ProductDetailColorSwatches.astro` (53 líneas) + `.stories.ts`: importado en `slug.astro` línea 34 pero **NUNCA se renderiza** (el picker vive inline en `slug.astro` líneas 358-389). El propio componente tiene header text indicando que es decorativo.
- Script legacy `data-toggle-details` (slug.astro l. 541-548): solo lo usa `ProductDetailDescription` (T4 lo elimina). Orphaned.

**Solución**: eliminar ambos.

**Cambios**:
- Eliminar archivos:
  - `frontend/src/components/storefront/products/ProductDetailColorSwatches.astro`
  - `frontend/src/components/storefront/products/ProductDetailColorSwatches.stories.ts`
- `slug.astro`:
  - Eliminar import línea 34.
  - Eliminar bloque `<script>` legacy (líneas 540-548).

**Commit**: `chore(pdp): eliminar ProductDetailColorSwatches y script data-toggle-details (muertos)`

**Verificación**:
- `cd frontend && bun run typecheck` → exit 0
- `git grep -n "ProductDetailColorSwatches\|data-toggle-details"` → debe estar vacío en `frontend/src/` (excepto el commit message).
- Manual: PDP debe renderizar idéntica sin esos componentes.

---

## T6 — Scroll restoration al recargar (Bug "no permanece en la sección")

**Problema**: el browser, al recargar, vuelve al top del documento. Si el usuario estaba leyendo la sección Reseñas (a media página) o las specs, se pierde contexto.

**Solución**: persistir `window.scrollY` (o el id de sección clickeado) en sessionStorage; al cargar, restaurar. Si la sección tiene `id`, hacer `scrollIntoView({block:'start'})` con `behavior:'auto'`; si no, `window.scrollTo(0, savedY)`.

**Cambios**:
- Nuevo `<script is:inline>` en `slug.astro` antes del cierre de `</StorefrontLayout>`:
  - Click handler delegado en `document` para todos los `<a href="#...">`: guarda `sessionStorage.setItem('pdp-scroll-target', href)` con el id destino.
  - Al cargar (`DOMContentLoaded` o antes): lee target/scrollY, hace `scrollIntoView` o `scrollTo`. Antes del primer paint (módulo top-level en lugar de DOMContentLoaded) si es posible, para evitar flash.
- Estrategia dual:
  - **Si hay `data-product-section` en un ancestor del target clickeado**: guardar el id de sección.
  - **Si no**: guardar `scrollY` en `beforeunload`.
- Restauración: leer de sessionStorage; si hay section id, `document.querySelector('#' + id)?.scrollIntoView({block:'start'})`; si no, `window.scrollTo(0, parseInt(saved))`.

**Decisión**: cookie NO viable aquí porque `scrollY` cambia频繁 y la cookie viaja en cada request. sessionStorage es local al tab y muere al cerrar — coincide con el patrón "permanece aquí mientras esté en la página".

**Commit**: `feat(pdp): scroll restoration via sessionStorage (permanece en sección al recargar)`

**Verificación**:
- Manual:
  - PDP Fender → scroll a sección Reseñas → recargar → debe permanecer en Reseñas.
  - Click en un anchor (`#reviews` etc.) → recargar → debe ir al anchor.
- Edge cases:
  - Cerrar tab → recargar en nueva tab → vuelve al top (correcto, sessionStorage scoped al tab).
  - Sin JS → vuelve al top (degradación aceptable).

---

## T7 — Verificación de reusabilidad (criterio transversal)

**Criterio**: la PDP debe renderizar correctamente con mocks mínimos. Para cualquier producto que NO provea `highlights`, `groupedSpecs`, `featuredArticle`, `financing`, `variantAttributes`, `bundleItems`, etc., la página no debe romper, no debe mostrar secciones vacías, no debe mostrar colores hardcoded.

**Cambios**:
- Auditar `slug.astro` (549 líneas) por:
  1. Strings hardcoded de Fender/Saber Alter (debe pasarse vía prop o i18n).
  2. Condicionales `!== undefined && length > 0` por cada bloque opcional (ya está hecho, verificar).
  3. Fallbacks en tests/storybook con mock mínimo (solo `Product` + `description + reviews + images`).
- Crear test story en Storybook con mock mínimo (solo campos base) → debe renderizar sin warnings.

**Sin commit nuevo** — esta verificación es un sub-criterio de aceptación de T1-T6. Si encuentro strings hardcoded, los arreglo en el commit de la task correspondiente.

**Verificación**:
- Storybook → abrir PDP con mock de "Producto X" (sin fields Gibson-specific) → debe verse limpio sin secciones vacías.

---

## T8 — Efecto lupa en la galería (feature nueva solicitada)

**Problema**: el usuario quiere efecto lupa al hacer hover sobre la imagen principal de la galería. Patrón clásico e-commerce (Amazon, Apple, Nike) que permite ver detalle ampliado de textura/material sin abrir lightbox.

**Decisión de patrón (confirmado por usuario)**: **A) Lente con background-image** — patrón premium tipo Apple/Amazon.

- **A) Lente con background-image** ✅ elegido: un cuadrado de ~180×180px sigue al cursor con la imagen ampliada vía `background-image` + `background-position` calculada según posición del cursor. UX premium, código ~80 líneas (script + CSS scoped). Reusa la URL existente (mismo `<img src>`).
- ~~B) Zoom con transform: scale~~ descartado.

**Cambios**:
- `ProductDetailGallery.astro`:
  - Añadir `<div class="zoom-lens" hidden aria-hidden="true">` que se posiciona absolute sobre el slide activo.
  - Script: en `mouseenter` del slide → mostrar lens + setear `background-image` con la misma URL del `<img>`. En `mousemove` → mover lens + calcular `background-position` proporcional. En `mouseleave` → ocultar lens.
  - `cursor: zoom-in` en el slide para affordance visual.
  - Sólo desktop (touch no tiene cursor). CSS `@media (hover: hover) and (pointer: fine)`.
  - Si `images` está vacío o son SVGs mockup → no activar (reusabilidad).
- No requiere imagen extra — reusa la URL existente (tradeoff: calidad si la original no es HD).
- Storybook: añadir variant `WithZoom` al gallery.

**Commit (mensaje tentativo)**: `feat(pdp): efecto lupa con lens en gallery desktop`

**Verificación**:
- Hover sobre imagen → lens aparece + sigue al cursor + muestra versión ampliada con zoom 2x.
- Touch (mobile) → no se muestra lens, comportamiento normal (scroll horizontal).
- `cd frontend && bun run typecheck` → exit 0
- `cd frontend && bun run storybook:test` → exit 0

---

## 8. Plan de ejecución (orden)

1. **T1** — specs accordion SSR (commit propio) → bug más visible, primero
2. **T2** — gallery scrollLeft (commit propio)
3. **T3** — ring slate-900 (commit propio)
4. **T4** — consolidar Description (commit propio)
5. **T5** — eliminar muertos (commit propio)
6. **T6** — scroll restoration (commit propio)
7. **T7** — auditoría reusabilidad inline en cada commit anterior
8. **T8** — efecto lupa en gallery (commit propio, después de T2 para no romper gallery)

Cada task = 1 commit conventional + verificación. Push y PR quedan a tu criterio.

---

## 9. Riesgos

- **T1 cookie SSR**: cookies caducan y deben respetar SameSite. Riesgo bajo (patrón estándar).
- **T2 scrollLeft**: si el contenedor no tiene layout al cargar (imágenes sin tamaño), `offsetLeft` es 0. Mitigación: `requestAnimationFrame` antes del set o esperar `load` event en imágenes.
- **T6 scroll restoration**: si el usuario llega por deep link con `#anchor`, el browser ya hace scroll al anchor → mi script debe respetar ese target primero.
- **T4 consolidación**: si la "fila inline" del aside tiene sizing distinto al card, podría romper layout. Mitigación: tests visuales manual en Storybook.

---

## 10. Follow-ups deferred (no en este plan)

- Wire backend (actualmente todo mock)
- Regenerar WebPs desde JPGs (pipeline ya existe en `scripts/convert-product-images.sh`)
- Banner CTA con link "Buscar tienda"
- Internationalization completo (algunos labels están hardcoded "Detalles", "Reseñas", etc.)
- Tests E2E con Playwright para los flujos críticos de PDP