# Plan de trabajo autónomo — sesión nocturna

> Este documento es la fuente de verdad si la sesión se corta por límite de
> contexto. Todo lo que esté sin marcar aquí, está pendiente.

## Estado al reanudar (2026-10-21 ~21:00)

Sesión retomada. Se corrigieron tres cosas del plan anterior y aparecieron
dos hallazgos que **refutan verificaciones previas**.

### ✅ Corregido este turno

| # | Qué | Verificación |
|---|-----|--------------|
| S1 | Swatch activo dejaba la card en blanco en `ProductCard.astro` | 313/313 tests, eslint limpio. **No verificado visualmente** — ver H2 |
| S2 | `aria-label="Variantes disponibles"` hardcodeado → prop `swatchGroupLabel` opcional | idem |
| S3 | Favicon: los 4 layouts **inventaban** la URL de branding | Verificado en Chromium: `<link rel="icon" type="image/svg+xml" href="/favicon.svg">` |
| S4 | Clavijero: plan de detección | Reportado abajo, read-only |

### ⚠️ Hallazgos que corrigen el plan anterior

**H1 — El favicon no era un "dato que falta", era código que mentía.**
Los 4 layouts (`Layout`, `AuthLayout`, `AdminLayout`, `StorefrontLayout`)
CONSTRUÍAN `/api/v1/storefront/assets/tenants/{slug}/branding/favicon.webp`
desde el slug. Eso **afirma que todo tenant tiene favicon**, y declara
`type="image/svg+xml"` sobre un `.webp`. Ahora el favicon es dato
declarado (`StoreBrand.faviconPath?`) con resolver único
(`lib/tenant-favicon.ts`) y fallback a `/favicon.svg`. El `logo.svg` 404
**no se tocó**: `StorefrontHeaderBrand.astro` ya degrada con `onerror`, y
es un hueco de datos de merchandising, no un bug.

**H2 — "swatches presentes (data-swatch-id)" era un FALSO POSITIVO.**
La sesión anterior_verificó swatches contando `data-swatch-id`. Esos 48
matches **no eran botones**: eran los selectores del `<style is:global>`.
Medición real del DOM del catálogo:

```
<article>                    : 24
botones [data-swatch-id]     : 0     <-- la sesión anterioruhr "48"
[data-role="variant"]        : 0
[data-swatch-active]        : 0
```

Causa: la API devuelve **1 sola variante** para la mayoría de productos, y
`shouldRenderSwatchRow` exige ≥2 (un swatch es un control muerto).

```
346 productos → nº de variantes: {1: 327, 2: 6, 3: 4, 4: 3, 6: 3, 7: 1, 8: 1, 15: 1}
productos con ≥2 variantes (con swatch row): 19
distribución en la página 1 (24 productos)  : {1: 24}   <-- CERO con swatches
```

**Los 19 productos que podrían mostrar swatches no están en la página 1.**

**H3 — El catálogo muestra 24 de 346 productos y la paginación es decorativa.**
`index.astro`: `currentPage = 1` hardcodeado, `per_page=200`, `slice(0, 24)`,
y `lastPage` se calcula sobre el array YA recortado → siempre 1 página.
El `Pagination` se renderiza pero no navega. Consecuencia directa de H2:
los 19 productos multi-variante son inalcanzables por la UI.

**H4 — Las imágenes de producto dan 404 en el navegador.**
Las URLs son `/api/v1/storefront/assets/...` (relativas). En dev:
- `curl :14000 (nginx) /api/v1/storefront/assets/...` → **200**, 62.862 bytes
- `curl :10321 (Astro dev) /api/v1/storefront/assets/...` → **404**

nginx sólo tiene `location ^~ /api/v1/storefront/assets/`; **no** hace
`proxy_pass` de las páginas al frontend (`location /` cae a un root estático
de Laravel). O sea: el entrypoint real del frontend en dev es `:10321`, y
ahí las imágenes relativas rompen. `astro.config` no define proxy de `/api`.
**Pendiente de decidir**: ¿gap de wiring de dev, o falta un proxy en Astro?

---

## Tarea 5 — Imagen principal muestra el clavijero (PLAN, no ejecutado)

**Hallazgo que refuta la premisa del brief**: la hipótesis de relación de
aspecto NO sirve. `headstock` es **100% cuadrado (1064/1064)** porque el
pipeline normaliza todo a 1:1. El caso confirmado
`1962-es-335-reissue-sixties-cherry/front-1600x1600.webp` es 1600×1600
exacto, con hash único, nombre correcto y `alt_text` correcto.

**El auditor no puede producir el defecto.** `pickPrimaryRole`
(`CatalogAssetsSeeder.php:380-392`) elige el rol de menor `ROLE_ORDER`
(`front=1, back=2, body=3, backbody=4, headstock=5`). Medido: **0 de 403**
portadas son `headstock` o posterior. Sólo un **nombre de archivo mentiroso**
puede producir una portada con clavijero.

Cota honesta: **≥1 y ≤403**. No se puede acotar más sin mirar las fotos.

Criterios, del más barato al más caro (todos con precisión medida):

| # | Criterio | Coste | Resultado medido |
|---|----------|-------|------------------|
| C1 | Rol ausente en `ROLE_ORDER` | SQL puro | **24 filas / 8 productos, 3 con `is_primary`**. Precisión 3/3: las 3 son portada = vista posterior. El regex `[a-z\-]+` de `AssetFilenameParser.php:53` acepta slugs pegados en vez de rechazarlos |
| C2 | Variante sin `front` en disco | un `ls` por producto | 65 de 420 grupos. **Mapa de riesgo, NO detector** |
| C3 | Binario idéntico bajo otro rol | `sha256sum` (~2 min) | 24 grupos, 60 filas, **2 primarias**. Refinado a `front`≡`headstock`: 1/1 |
| C4 | `WxH` del nombre ≠ píxeles reales | `identify` (~70 s) | 53 archivos (0,52%). **Precisión 0/8** para este defecto — los 8 `front` no cuadrados son pickups |
| C5 | Ratio de bytes `front`/`headstock` | `stat` | Mediana 0,7043. El caso confirmado queda en **percentil 33,2**. Falso negativo demostrado → **NO implementar** |
| C6 | Hash perceptual / embedding | GPU, minutos | **Único que ataca el residuo real.** Precisión NO MEDIDA |

**Hit automatizable confirmado**: `product_images.id = 5249`
(`j-45-standard-12-string`) — `front` byte-idéntico a un `headstock` del
mismo producto. Es **INFERENCIA fuerte**, no hecho medido: la igualdad de
bytes con un archivo que el catálogo etiqueta `headstock` no prueba el
contenido visual. También `id = 9933`, pero es otro defecto (foto genérica
reutilizada, no un clavijero).

**Residuo humano**: los 403 portada. Ninguna métrica disponible discrimina.
Un humano mira **345 `front` + las 4 restantes**, no las 9.964.

**Siguiente paso único**: validar C6 sobre los 2 casos conocidos (id 5249
→ debe dar positivo; `1962-es-335-reissue-sixties-cherry` id 173 → debe dar
positivo) contra una muestra de 20 `front` sanos. Si C6 funciona, el residuo
humano baja de 403 a decenas. Si no, el humano mira 345 y el reporte lo dice.

**NO hacer**: renombrar assets a mano (`StorageKeyDeriver.php:38-44` deriva
el path del hash del **contenido** — renombrar rompe la derivación y el
`image_url` en BD); borrar assets (un `front` con clavijero sigue siendo la
única imagen de su variante); tocar `is_primary` a mano (se revierte en el
próximo `seed:catalog-assets`); correr `assets:audit-gallery --fix`
(inserta filas, y esta tarea fue read-only).

---

## Tarea 6 — Verificación y cierre

- [x] `bun run typecheck` → 0 errores
- [x] `bun run test` → **313/313** (295 previos + 18 de `tenant-favicon`)
- [x] `bunx eslint` sobre los archivos tocados → 0 errores
- [x] Chromium real: favicon = `/favicon.svg`, sin MIME mentiroso
- [x] Chromium real: catálogo = 24 `<article>`, **1** `astro-island`
      (NO es `StorefrontProductsList` → sin duplicación, H2 confirmado)
- [ ] Chromium real del fix de swatch activo → **BLOQUEADO por H2/H3**:
      ningún producto alcanzable por la UI tiene ≥2 variantes
- [ ] Decidir H4 (404 de imágenes: gap de wiring de dev vs falta proxy)
- [ ] Arreglar H3 (paginación decorativa) → desbloquea la verificación visual
- [ ] `php artisan test` del área de consola (backfill de precios)

---

## Trampas del entorno — NO repetirlas

1. Los tests exigen `-e DB_DATABASE=instrumentos-musicales-la-voz_test`.
   La BD de desarrollo fue truncada 3 veces en este proyecto.
2. El container monta `data/catalog-meta`, **no `data/`**. `backend/data/` es
   root y no escribible. El override quedó en `backend/config/`.
3. `base_path('../data/...')` funciona en el host y **no** en el container.
4. `curl` **no** verifica la UI (no ejecuta JS), pero **sí** sirve para
   verificar el HTML SSR y para contrastar puertos.
5. **Playwright:** el `Host` en `extraHTTPHeaders`Provoca
   `net::ERR_INVALID_ARGUMENT` — CDP prohíbe sobreescribir `Host`. Usar
   `launch({ args: ['--host-resolver-rules=MAP <host> 127.0.0.1'] })`.
6. **Playwright:** la versión de `playwright` y la build de Chromium deben
   coincidir (`chromium_headless_shell-1248`), si no falla el launch.
7. Para contar **swatches reales** hay que contar
   `<button data-swatch-id>` o `locator(...).count()`. Contar la string
   `data-swatch-id` sobre el HTML cuenta los **selectores CSS** del
   `<style is:global>` y da un falso positivo.
8. Para contar atributos Astro que sólo existen a veces, usar
   `re.findall(...).count()` sobre el HTML, nunca `grep -c` (cuenta líneas).
9. nginx en dev (`:14000`) sirve `/api/v1/storefront/assets/` pero **no**
   las páginas de Astro. El frontend se accede por `:10321`.
10. Los mensajes de commit van **sin tildes** y **por archivo**, nunca por
    heredoc: una tilde se corrompió una vez.
11. `php -l` no detecta named arguments inválidos.