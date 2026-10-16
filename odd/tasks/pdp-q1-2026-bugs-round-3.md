# Plan: PDP Q1 2026 — bugs visuales ronda 3

> **Status**: pendiente aprobación. Branch de trabajo desde `master` (HEAD `9228f05c`).
>
> **Scope**: 5 fixes visuales reportados por el usuario.
>
> **Engram mirror**: `odd/pdp-q1-2026-bugs-round-3/tasks`

---

## 0. Diagnóstico

| # | Reporte | Causa probable |
|---|---|---|
| 11 | Imagen PDP no se ve nítida (referencia guitargear muestra detalles) | Falta `srcset` responsive (los webps 400/800/1200/1600 ya están commiteados pero el `<img>` siempre carga 1600×730). Sin `sizes` attribute. |
| 12 | Accordion specs (Cuerpo) abierto al recargar se cierra | Mi fix `4d2fa155` (script is:inline + sync defensivo) podría no estar aplicándose por cache del browser, o cookie no se está seteando. Debug con playwright. |
| 13 | Punto redondo de color al lado de "Halo Metallic" en Acabado | Swatch decorativo (3×3 round-full con hex background) — el usuario lo confunde con un bullet. Quitar. |
| 14 | Botón like de reseñas + like del producto no funcionan | Like de reseñas (T9): auth gate falla porque Sanctum usa httpOnly cookies (el frontend no puede detectar auth via localStorage). Like del producto (ProductCTAs): botón decorativo SIN handler JS — click no hace nada. |
| 15 | Reseñas deben tener opción para colapsar y desplegar | El botón "Cargar más" YA existe y aparece con 8 reseñas. Quizás el user quiere collapse/expand por reseña individual, o el botón no se está mostrando. |

---

## T11 — Imagen nitidez con `srcset` responsive

**Archivo**: `frontend/src/components/storefront/products/ProductDetailGallery.astro`

**Cambios**:
- En el `<img>` del slide y de los thumbnails, agregar `srcset` con los 4 tamaños commiteados (`-400x183`, `-800x365`, `-1200x547`, `-1600x730`).
- Agregar `sizes` attribute al `<img>` principal: `(min-width: 1024px) 1160px, 100vw`.
- Para thumbnail: `sizes="(min-width: 640px) 72px, 64px"`.
- Considerar `fetchpriority="high"` para la primera imagen (eager).

**Helper**:
- Crear helper privado `imageSrcset(url, variant, nn)` que retorna el srcset string.

**Commit**: `feat(pdp): srcset responsive en gallery (mejora nitidez)`

**Verificación**: typecheck pasa.

---

## T12 — Accordion specs persiste (debug + sessionStorage fallback)

**Archivo**: `frontend/src/components/storefront/products/ProductSpecificationsByCategory.astro`

**Estrategia**:
1. Agregar fallback a `sessionStorage` por si la cookie falla (browser policy, third-party, etc.)
2. El server lee cookie (ya implementado en T1). Si la cookie no existe, leer sessionStorage NO es posible en SSR. Mantener cookie como fuente SSR.
4. El cliente sincroniza ambas: cookie PRIMARIO, sessionStorage BACKUP. Toggle escribe a ambas. Si cookie falla (try/catch), sessionStorage persiste.

**Verificación con playwright**:
- Reproducir el bug exactamente: abrir Cuerpo → reload → verificar si sigue abierto
- Si playwright confirma que el bug NO está en mi código (solo cache del user), agregar defensive sessionStorage igual para robustez

**Commit**: `fix(pdp): sessionStorage fallback en specs accordion`

---

## T13 — Quitar swatch bullet de Acabado [INLINE]

**Archivo**: `frontend/src/pages/[locale]/products/[slug].astro` línea ~347

**Cambios**: Eliminar el `<span>` con `inline-block h-3 w-3 rounded-full border border-slate-200` y el `{selectedFinish?.hex !== undefined && ...}` wrapper. Solo dejar el texto del variant.

**Commit**: `fix(pdp): quitar swatch bullet de Acabado (ruido visual)`

**Verificación**: typecheck pasa.

---

## T14 — Like de producto (ProductCTAs) funciona

**Investigación previa**:
- ¿Existe endpoint `/api/v1/wishlist/{product}` en backend? (buscar)
- Si no existe, agregar al plan

**Cambios**:
- Agregar handler JS al botón Favorite en ProductCTAs.astro
- Auth gate (mismo patrón que like de reseñas)
- Si no auth → redirect a login
- Si auth → fetch POST/DELETE al endpoint

**Commit**: `feat(pdp): handler wishlist en ProductCTAs (auth + API)`

---

## T15 — Reseñas colapsar/desplegar (verificar)

**Investigación previa**:
- El botón "Cargar más" se renderiza si `reviews.length > initialCount (3)`. Con 8 reseñas, debería aparecer.
- Si el user quiere collapse/expand individual, eso es otra feature (`<details>` por reseña).

**Estrategia**:
1. Verificar manualmente que el botón aparece con 8 reseñas (playwright o screenshot)
2. Si aparece, el user quizás quiere `<details>` por reseña individual — preguntar
3. Si no aparece, debug

**Commit**: (depende de hallazgo)

---

## Riesgos

- **T11 srcset**: el path actual es `...-1600x730-NN.webp`. Para srcset necesito las 4 versiones. Si la URL base no incluye el tamaño, tengo que derivar las 4 URLs.
- **T12 sessionStorage fallback**: solo funciona en cliente, server no puede leer sessionStorage. Cookie sigue siendo la fuente SSR. El sessionStorage es fallback para el cliente.
- **T14 wishlist**: si no hay endpoint backend, hay que crearlo (T8b). Más trabajo.
- **T15 reseñas**: si el user quiere collapse individual, eso es una feature nueva con tests visuales.

---

## Follow-ups deferred

- Review replies (admin responde a reseña)
- Review photos (upload)
- ProductDetailVariant con status constraint del backend
- Wire backend reseñas a GET público