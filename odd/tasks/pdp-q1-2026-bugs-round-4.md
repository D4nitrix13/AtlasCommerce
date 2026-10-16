# Plan: PDP Q1 2026 — bugs visuales ronda 4 (acabo / imagen / accordion / reseñas)

> **Status**: pendiente aprobación. Branch de trabajo desde `master` (HEAD `76219cd0`).
>
> **Scope**: 4 fixes visuales reportados.
> **Tono**: el usuario está molesto con el accordion (2 fixes previos no resolvieron). Esta ronda requiere fix DEFINITIVO, no parche.
>
> **Engram mirror**: `odd/pdp-q1-2026-bugs-round-4/tasks`

---

## 0. Diagnóstico

| # | Reporte | Causa raíz confirmada |
|---|---|---|
| **16** | Acabados picker no se ve como Nike (4 thumbnails pequeños uniformes) | El picker actual (slug.astro líneas 358-389) renderiza thumbnails 40×40 (h-10 w-10) con thumbnails de la galería. Quiere thumbnails más pequeños (32×32 o 48×48) con aspect uniforme y todos los colores/variantes del producto lado a lado. |
| **17** | Imagen instrumento chiquita, cortada, pixelada cuando hace zoom | El webp fuente es **1600×730**. El slide renderiza a ~708×321 (desktop). La lupa (T8) usa `clientWidth × ZOOM_FACTOR=2.5` = `1770×802` para `background-size`. **El browser up-scale la fuente 1600×730 → 1770×802 → pixelación visible.** Solución: usar `naturalWidth × 1.0` (no upscale), zoom efectivo = ratio natural/render = ~2.26x en desktop. |
| **18** | Accordion specs no mantiene estado al recargar (2 fixes previos fallaron) | Cookie SSR + sessionStorage fallback NO funcionan en el browser del user. Diagnóstico: el user puede tener cookies deshabilitadas, OR SameSite issue cross-domain, OR `document.cookie.includes('sanctum_token')` falla por httpOnly. **Fix DEFINITIVO**: triple redundancia — (a) cookie SSR + (b) `sessionStorage` PRIMARIO en cliente + (c) URL hash `#spec=cuerpo,mástil` como backup visual + reload-restore. Si los 3 fallan, falla de verdad. |
| **19** | "Ver todas las reseñas" redirige a una vista que no existe | El link (`<a href={viewAllHref}>`) apunta a `/${locale}/products/${slug}/reviews`, pero ESA RUTA NO EXISTE en `pages/`. Click → 404. |
| **20** | Componente de reseñas NO debe tener botón colapsar/desplegar | El botón "Cargar más"/"Ver menos" con initialCount=3 es un anti-patrón UX en reseñas. Mejor patrón: mostrar todas las reseñas siempre, OR vista dedicada con paginación. |

---

## T16 — Acabados picker estilo Nike (thumbnails pequeños uniformes)

**Archivo**: `frontend/src/pages/[locale]/products/[slug].astro`

**Cambios**:
- El picker de Acabado actualmente renderiza `<a class="block h-10 w-10 rounded-md border bg-slate-50">` con un `<img>` 40×40.
- Cambiar a `<span>` (no link) con `<img>` 48×48 (h-12 w-12), aspect-square (`object-cover`), border 2px, separador gap-1.5.
- Cada thumbnail muestra el primer webp del variant con `object-cover` para que se vea uniforme.
- Borde primary (`border-[var(--primary,#475569)]`) para el active.
- Si el variant no tiene imagen (placeholder), mostrar swatch de color hex.

**Commit**: `feat(pdp): acabados picker estilo Nike (uniformes pequeños)`

**Verificación**: typecheck pasa. Visual: thumbnails uniformes ~48×48.

---

## T17 — Imagen nítida (lupa sin upscale)

**Archivo**: `frontend/src/components/storefront/products/ProductDetailGallery.astro`

**Cambios**:
- En el handler `onEnter` del T8, cambiar:
  ```js
  // ANTES (causa pixelación — upscale de la fuente):
  const w = img.clientWidth;  // 708
  const h = img.clientHeight;  // 321
  lens.style.backgroundSize = `${w * ZOOM_FACTOR}px ${h * ZOOM_FACTOR}px`;  // 1770×802

  // DESPUÉS (sin upscale, zoom efectivo = ratio natural/render):
  const w = img.naturalWidth;  // 1600
  const h = img.naturalHeight;  // 730
  lens.style.backgroundSize = `${w}px ${h}px`;  // 1600×730 = archivo fuente
  ```
- `ZOOM_FACTOR` se vuelve 1.0 (o se elimina). El zoom visual efectivo es `naturalWidth / clientWidth = 1600/708 ≈ 2.26x` en desktop, sin upscale.

**Commit**: `fix(pdp): lupa sin upscale (zoom desde naturalWidth, no clientWidth)`

**Verificación**:
- Manual: PDP Fender → hover sobre imagen → lupa muestra imagen sin pixelación.
- Console: `background-size: 1600px 730px` (no 1770x802).
- typecheck pasa.

---

## T18 — Accordion specs — fix DEFINITIVO con triple redundancia

**Archivos**:
- `frontend/src/components/storefront/products/ProductSpecificationsByCategory.astro`
- `frontend/src/pages/[locale]/products/[slug].astro` (opcional — agregar `id` data-attribute a cada `<details>` para hash anchor)

**Estrategia triple redundancia**:

1. **Cookie SSR** (ya implementado en T1): el server lee la cookie y renderiza `<details open>`. Sigue siendo la fuente SSR primaria para evitar flash.

2. **sessionStorage PRIMARIO en cliente** (ya implementado en T12, ahora como prioritaRIO en lugar de fallback): el cliente lee sessionStorage al cargar y sincroniza el DOM con el state. Si sessionStorage está vacío, lee cookie como fallback.

3. **URL hash como backup** (NUEVO): cada `<details>` tiene un `id` único (`spec-cuerpo`, `spec-mástil`, etc.). Al togglear, actualiza `location.hash` con los IDs abiertos separados por coma: `#spec=cuerpo,mástil`. Al cargar, el script lee el hash y abre esos `<details>`. Esto SOBREVIVE incluso si cookies y sessionStorage están deshabilitados.

**Implementación**:
- En el `<script is:inline>`:
  ```js
  const HASH_PREFIX = '#spec=';
  
  // Lee state de session PRIMARIO, cookie en fallback, hash en último recurso.
  const readState = () => {
    let state = readSession();
    if (state.length === 0) state = readCookie();
    if (state.length === 0) {
      const hash = window.location.hash;
      if (hash.startsWith(HASH_PREFIX)) {
        state = hash.slice(HASH_PREFIX.length).split(',').filter(Boolean);
      }
    }
    return state;
  };
  
  // En el listener de toggle, escribe a session PRIMARIO, cookie fallback, hash backup.
  const persist = (state) => {
    writeSession(state);
    writeCookie(state);
    // Update hash without scroll jump.
    const hash = state.length > 0 ? `${HASH_PREFIX}${state.join(',')}` : '';
    if (hash !== window.location.hash) {
      history.replaceState(null, '', hash || window.location.pathname);
    }
  };
  ```

- En el server-render del componente, añadir `id` al `<details>`:
  ```astro
  <details
    id={`spec-${specId}`}
    ...
  >
  ```

**Commit**: `fix(pdp): accordion specs con triple redundancia (session + cookie + URL hash)`

**Verificación**:
- Manual: PDP Fender → abrir Cuerpo → reload → debe estar abierto.
- Test con cookies deshabilitadas en el browser (DevTools → Application → Cookies → Block): sessionStorage + hash deberían mantener el estado.
- typecheck pasa.

---

## T19 — Crear vista `/[locale]/products/[slug]/reviews.astro`

**Archivo**: `frontend/src/pages/[locale]/products/[slug]/reviews.astro` (NUEVO)

**Contenido**:
- Lee el producto desde `findProductBySlug(slug)` (misma lógica que `[slug].astro`).
- Renderiza TODAS las reseñas (sin truncado, sin initialCount).
- Sin filtro chips (la vista "all" se queda en el componente principal; esta vista es la "see all").
- Paginación server-side: 20 reseñas por página (si el producto tiene >20).
- Breadcrumb: Productos > [Producto] > Reseñas.
- Cada reseña: autor, fecha, rating, título, body, badge compra verificada, botón like (mismo que PDP).
- CTA "Escribir reseña" en el header.
- Link "Volver al producto" abajo.

**Sin filtros activos**: esta vista es la lista COMPLETA. Si el user quiere filtrar, va al PDP y filtra ahí.

**Commit**: `feat(pdp): vista dedicada de reseñas (/{locale}/products/{slug}/reviews)`

**Verificación**:
- Click "Ver todas las reseñas" en PDP → abre la vista sin 404.
- Renderiza todas las reseñas del producto.
- typecheck pasa.

---

## T20 — Limpiar ProductReviews (sin colapsar/desplegar)

**Archivo**: `frontend/src/components/storefront/products/ProductReviews.astro`

**Cambios**:
- Eliminar el botón "Cargar más"/"Ver menos" (líneas 235-259).
- Eliminar la prop `initialCount` (ya no aplica).
- Eliminar la lógica de `applyToggleState` (líneas 353-373).
- Mantener: filter chips, lista de reseñas, botón like, link "Ver todas" (ahora apunta a vista real).
- Actualizar `applyFilter` para no considerar `withinLimit` (siempre muestra todas las del rating).
- Eliminar el script inline que maneja el toggle (líneas 346-373 del script).

**UI final del componente**:
- Header con rating promedio
- Filter chips (Todas, 5★, 4★, 3★, 2★, 1★)
- Lista de reseñas (TODAS, sin colapso)
- Botón like por reseña
- Botón "Escribir reseña"
- Link "Ver todas las reseñas" → vista dedicada (T19)

**Commit**: `refactor(pdp): quitar botón colapsar/desplegar de reseñas (mostrar todas)`

**Verificación**:
- Manual: PDP Fender → reseñas → 8 reseñas visibles de inmediato, sin "Cargar más".
- typecheck pasa.

---

## 21. Plan de ejecución (orden)

1. **T16** (acabados picker) — inline, 1 archivo
2. **T17** (imagen nítida) — inline, 1 archivo
3. **T18** (accordion DEFINITIVO) — inline, 1 archivo
4. **T19** (vista reseñas dedicada) — delegado, 1 archivo nuevo
5. **T20** (cleanup reseñas) — inline, 1 archivo

---

## 22. Riesgos

- **T16**: si el variant no tiene primera imagen, mostrar swatch de color hex como fallback.
- **T17**: el `naturalWidth/Height` puede ser 0 si la imagen aún no cargó (raro pero posible). Fallback a `clientWidth/Height` mantiene la lógica anterior.
- **T18**: hash URL puede ser feo en la barra de direcciones. Trade-off UX: persistencia > estética de URL.
- **T19**: si el slug tiene caracteres especiales, el routing de Astro maneja URL encoding automáticamente. Verificar.
- **T20**: si el producto tiene 100+ reseñas, la lista se vuelve larga. Mitigación futura: paginación virtual.

---

## 23. Follow-ups deferred

- Extraer `<ReviewLikeButton>` a sub-componente (ProductReviews.astro sigue >250 líneas hasta T20)
- Review replies (admin responde a reseña)
- Review photos (upload)
- Wire reseñas al backend (reemplazar mocks)
- Sort reseñas (más recientes / más útiles)