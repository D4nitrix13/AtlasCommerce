# Plan: Reseñas + selectors condicionales + likes

> **Status**: pendiente aprobación. Branch de trabajo desde `master` (HEAD `c17fcf1b`).
>
> **Scope**: 4 cambios relacionados en PDP reseñas + variantes por nicho + feature like.
> Crítico: respetar reglas backend Laravel Boost (`.ai/rules/index.md` no existe aún, pero `backend/AGENTS.md` aplica Boost Guidelines + reglas del proyecto: named args, strict types, factories, Pint format, PHPUnit).
>
> **Engram mirror**: `odd/pdp-q1-2026-reviews-selectors-likes/tasks`

---

## 0. Contexto y motivación

El usuario probó la PDP Fender y pidió 4 cosas:

1. **Más reseñas por defecto** — actualmente 3 (Roberto M., Laura S., Daniel P.); quiere +5 mínimo para que se note el "Cargar más".
2. **Botón "desplegar" en reseñas** — YA existe en código (`ProductReviews.astro:235-259`); solo aparece si hay más de `initialCount=3`. Mi recomendación: **load-more > paginación**.
3. **Bug del color verde** — el botón "Todas" tiene clases hardcoded con `bg-primary` SIEMPRE. Cuando se selecciona 5★/4★, ese botón sigue verde. Es bug, no intencional.
4. **Selectors condicionales** — para La Voz Instrumentos: NO mostrar `ProductFitSelector` (new/used — instrumentos nuevos siempre) NI `ProductSizeGrid` (4/4, 3/4 — no aplica a bajos Fender). El proyecto es multi-tenant: Nike SÍ usa tallas, otros nichos también.

Plus: **botón like en reseñas**, requiere auth (registrado), persiste en BD.

### Estado del backend (verificado)

- ✅ `product_reviews` migration existe (`backend/database/migrations/support/2026_06_22_032048_create_product_reviews_table.php`) con FK a `products`, `customers`, `stores`, soft deletes, índices.
- ❌ No hay seeder que pueble reseñas.
- ❌ No hay `ProductReview` Eloquent model en `backend/app/Models/Support/` (buscar). Hay `backend/app/Services/Support/SupportTicketService.php` que sugiere namespace Support.
- ❌ No hay migration para review likes.
- ❌ No hay campo `has_condition`/`has_sizes` en `products` table.

### Estado del frontend (verificado)

- ✅ `ProductReviews.astro` tiene filter chips + load-more + view-all link.
- ❌ Bug: botón "Todas" hardcoded active.
- ❌ Mocks Fender solo con 3 reseñas (`FENDER_PLAYER_FUSION_BASS_DETAIL.reviews`).
- ❌ `ProductFitSelector` + `ProductSizeGrid` se renderizan SIEMPRE en `slug.astro` líneas 418-429.

---

## 1. Decisiones que pido al usuario (2 forks reales)

### Fork A: Load-more vs paginación

**Mi recomendación: load-more** (SÍ)

Razones (Baymard, Nielsen Norman, Amazon/Apple/Nike patterns):
- Engagement: usuario no pierde contexto (no navega a página 2)
- Mobile-friendly: menos taps, mismo viewport
- Performance: solo renderiza cuando se pide
- E-commerce estándar para reseñas (la paginación es para catálogos >100 items)
- Ya implementado en código (`ProductReviews.astro:235-259`)

**Alternativa (NO recomendada):** paginación numerada — útil solo si reseñas >100 y hay SEO/analytics granulares.

### Fork B: Cómo hacemos que fit/size sean condicionales

**Mi recomendación: agregar campos `has_condition` (bool) + `has_sizes` (bool) a `products` table**

Razones:
- Configurable por producto (no por tenant): un bajo Fender NO tiene tallas; una guitarra Gibson SÍ; una camisa Nike SÍ tiene tallas. La condición new/used aplica solo a algunos nichos (ropa usada, autos, antigüedades).
- Migration simple: `ALTER TABLE products ADD COLUMN has_condition BOOLEAN DEFAULT TRUE, ADD COLUMN has_sizes BOOLEAN DEFAULT FALSE`
- Frontend consulta `detail.hasCondition` / `detail.hasSizes` para render condicional
- Mantiene multi-tenancy: cada tenant define su config por producto

**Alternativas evaluadas:**
- ❌ Usar `category` para inferir (frágil: categoría "Bajo eléctrico" no implica nada sobre tallas)
- ❌ Usar JSON config en `category` (over-engineering)
- ❌ Tags en `products.tags` (mezcla concerns)

---

## 2. Tasks

### T1 — Backend: Seeder reseñas Fender

**Por qué**: BD está vacía para `product_reviews`. Necesitamos datos reales para que la PDP muestre reviews del seeder (cuando el endpoint exista) y para tests.

**Cambios**:
- Crear `backend/database/seeders/FenderPlayerFusionBassReviewsSeeder.php` que puebla 8 reseñas:
  - Ratings variados: 5★, 5★, 4★, 4★, 5★, 3★, 5★, 4★ (promedio ≈ 4.4)
  - Clientes ficticios (mínimo 3 customers existentes o crear nuevos)
  - Comentarios realistas en español, coherentes con bajo eléctrico
  - `is_approved: true` para todas
  - Fechas distribuidas en últimos 3 meses
- Idempotente: `updateOrCreate` por `(product_id, customer_id, reviewed_at)` o borrar antes de re-insertar
- Idempotente: corre múltiples veces sin duplicar

**Commit**: `feat(backend): seeder reseñas Fender Player Fusion (8 reviews)`

**Verificación**:
- `cd backend && php artisan db:seed --class=FenderPlayerFusionBassReviewsSeeder` → exit 0
- `SELECT count(*) FROM product_reviews WHERE product_id = <fender>` → 8
- `vendor/bin/pint --dirty --format agent` → exit 0
- `php artisan test --compact tests/Feature/Api/Storefront/` (si existe test de reseñas)

---

### T2 — Backend: Migration `has_condition` + `has_sizes` en products

**Por qué**: Para que cada producto declare qué selectors aplican (multi-nicho).

**Cambios**:
- Nueva migration `backend/database/migrations/catalog/2026_XX_XX_alter_products_add_variant_selectors.php`:
  - `$table->boolean('has_condition')->default(true)`
  - `$table->boolean('has_sizes')->default(false)`
- Actualizar `backend/app/Models/Catalog/Product.php`:
  - Agregar a `$fillable`: `'has_condition', 'has_sizes'`
  - Agregar a `$casts`: `'has_condition' => 'boolean', 'has_sizes' => 'boolean'`
- Test mínimo: factory o seeder que cree un producto con `has_sizes: true`

**Commit**: `feat(backend): products.has_condition + has_sizes (multi-nicho)`

**Verificación**:
- `php artisan migrate` → exit 0
- Re-correr seeders existentes → exit 0 (default true/false no rompe nada)
- `vendor/bin/pint --dirty --format agent` → exit 0

---

### T3 — Backend: Actualizar seeder Fender con selectors

**Por qué**: Bajo Fender NO tiene tallas. Instrumentos nuevos → condición=true (es default, pero explícito).

**Cambios**:
- En `FenderPlayerFusionBassSeeder::ensureProduct()`, agregar `has_condition: true, has_sizes: false`
- (Idempotente: el `updateOrCreate` actualiza los flags)

**Commit**: `chore(backend): seeder Fender has_sizes=false (instrumentos sin tallas)`

**Verificación**:
- `php artisan db:seed --class=FenderPlayerFusionBassSeeder` → exit 0
- `SELECT has_condition, has_sizes FROM products WHERE slug='fender-player-fusion-bass-hh'` → 1, 0

---

### T4 — Frontend: Expandir reseñas mock Fender a 8

**Por qué**: PDP usa mocks hasta que exista endpoint. Necesitamos >=8 reseñas en `FENDER_PLAYER_FUSION_BASS_DETAIL.reviews` para mostrar el botón "Cargar más".

**Cambios**:
- `frontend/src/data/product-detail-mock.ts` líneas 456-... : añadir 5 reseñas más (total 8)
- Variedad: 5★ × 4, 4★ × 3, 3★ × 1 (promedio ~4.4, realista)
- Títulos y comentarios variados, en español, sobre bajo eléctrico (Custombucker pickups, Halo Metallic, mástil, etc.)
- Fechas distribuidas últimos 90 días
- Todos con `verified: true` (para que se vea el badge)
- NO tocar reseñas de SABER_ALTER_DETAIL (ese producto es figure, mock diferente)

**Commit**: `feat(pdp): expand reseñas mock Fender (8 reviews para load-more)`

**Verificación**:
- `bun run typecheck` → exit 0 (mismos 5 errors preexistentes)
- Manual: PDP Fender → debe mostrar 3 reseñas + botón "Cargar más" → click → muestra 5 más.

---

### T5 — Frontend: Fix bug color verde en ProductReviews

**Por qué**: El botón "Todas" tiene `bg-primary` hardcoded. Cuando se selecciona otro filtro, sigue verde. Bug real.

**Cambios**:
- `frontend/src/components/storefront/products/ProductReviews.astro` líneas 139-146:
  - Cambiar de clases hardcoded a `class:list` con `data-[active=true]:` para que JS controle el active state
  - Igualar patrón con los otros 5 botones (5★, 4★, etc.)
  - Mantener `aria-pressed="true"` inicial (server-render con Todas activo)
- Verificar que el JS (líneas 330-335) ya setea `data-active` correctamente en TODOS los buttons (lo hace)
- Sin cambios al CSS scoped ni al script

**Commit**: `fix(pdp): active state del filter Todas controlado por JS`

**Verificación**:
- Manual: PDP Fender → click "5★" → "5★" se pone verde, "Todas" vuelve a outline
- bun run typecheck → exit 0

---

### T6 — Frontend: Render condicional fit/size en slug.astro

**Por qué**: Bajo Fender NO debe mostrar selectores de condición nueva/usada (nicho instrumentos nuevos siempre) ni de tallas 4/4/3/4 (no aplica). Configurable por producto.

**Cambios**:
- `frontend/src/data/product-detail-mock.ts`:
  - Agregar `hasCondition: true` + `hasSizes: false` a `FENDER_PLAYER_FUSION_BASS_DETAIL` (instrumento)
  - Agregar `hasCondition: false` + `hasSizes: true` a `SABER_ALTER_DETAIL` (figura: tiene tallas, no condición)
  - Type: extender `ProductDetailData` con los nuevos campos
- `frontend/src/pages/[locale]/products/[slug].astro` líneas 418-429:
  - Wrap `<ProductFitSelector>` con `{detail.hasCondition && (...)}`
  - Wrap `<ProductSizeGrid>` con `{detail.hasSizes && (...)}`
  - Eliminar las constantes hardcoded `CONDITION_OPTIONS` y `SIZE_OPTIONS` del frontmatter (mover a los mocks o hacerlas derivadas)

**Commit**: `feat(pdp): render condicional de fit/size selectors (hasCondition/hasSizes)`

**Verificación**:
- Manual: PDP Fender → NO debe mostrar "Nuevo/Usado" ni "Tamaño"
- Manual: PDP Saber Alter → SÍ debe mostrar tallas (S/M/L), NO condición
- bun run typecheck → exit 0

---

### T7 — Backend: Migration + Model review likes

**Por qué**: Likes de reseñas con auth requerida + persistencia.

**Cambios**:
- Nueva migration `backend/database/migrations/support/2026_XX_XX_create_product_review_likes_table.php`:
  - `$table->id()`
  - `$table->foreignId('product_review_id')->constrained('product_reviews')->cascadeOnDelete()`
  - `$table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete()`
  - `$table->timestamps()`
  - `unique(['product_review_id', 'customer_id'])` — un customer solo puede likear una vez
  - Índices: `product_review_id`, `customer_id`
- Crear `backend/app/Models/Support/ProductReviewLike.php`:
  - Relations: `review()`, `customer()`
  - `$fillable`: `product_review_id`, `customer_id`
- Crear factory `backend/database/factories/Support/ProductReviewFactory.php` (si no existe) y `ProductReviewLikeFactory.php`

**Commit**: `feat(backend): migration + model review likes`

**Verificación**:
- `php artisan migrate` → exit 0
- `php artisan tinker --execute 'dump(App\Models\Support\ProductReviewLike::factory()->create());'`
- `vendor/bin/pint --dirty --format agent` → exit 0

---

### T8 — Backend: API endpoint like/unlike

**Por qué**: Persistir el toggle de like desde frontend.

**Cambios**:
- Crear `backend/app/Http/Controllers/Api/Storefront/ProductReviewLikeController.php`:
  - `POST /api/storefront/reviews/{review}/like` → crea like (idempotente con `firstOrCreate`)
  - `DELETE /api/storefront/reviews/{review}/like` → elimina like
  - Auth middleware: `auth:customer` (Laravel Sanctum o session-based)
  - Respuesta JSON: `{ liked: true|false, count: N }`
- Crear rutas en `backend/routes/api.php` (o donde corresponda):
  - `Route::post('/reviews/{review}/like', ...)` con middleware auth
  - `Route::delete('/reviews/{review}/like', ...)` con middleware auth
- Rate limit (opcional): 60 likes/min por user

**Commit**: `feat(backend): API like/unlike con auth requerida`

**Verificación**:
- Test PHPUnit: `tests/Feature/Api/Storefront/ProductReviewLikeTest.php`
  - Like sin auth → 401
  - Like con auth → 200 + row creada
  - Like duplicado → idempotente
  - Unlike → row eliminada
  - Like a review inexistente → 404
- `php artisan test --compact tests/Feature/Api/Storefront/ProductReviewLikeTest.php` → exit 0
- `vendor/bin/pint --dirty --format agent` → exit 0

---

### T9 — Frontend: Botón like en ProductReviews

**Por qué**: UX del like en cada reseña.

**Cambios**:
- `frontend/src/components/storefront/products/ProductReviews.astro`:
  - Nuevo botón en cada `<li>` (después del body): corazón + count
  - Estilos: outline si no likedeado, fill red-500 si likedeado (estándar)
  - Estado inicial: contado desde `review.likesCount` (mock por ahora, después del backend)
  - Si no auth → click redirige a `/login?redirect=<current>` (placeholder; o muestra prompt)
  - Si auth → fetch POST/DELETE, actualiza UI optimistamente
- Mock: añadir `likesCount: number` + `likedByCurrentUser: boolean` a `ProductReview` interface (frontend type)
- Añadir 8 reseñas con `likesCount` variados (3-15)

**Commit**: `feat(pdp): botón like en reseñas (frontend mock + auth gate)`

**Verificación**:
- Manual: PDP Fender (mock) → cada reseña muestra corazón + count
- bun run typecheck → exit 0
- Cuando exista endpoint (T8), cambiar fetch URLs

---

### T10 — Frontend: i18n labels de likes + login prompt

**Por qué**: Internacionalización.

**Cambios**:
- `frontend/src/i18n/es.json` + `en.json`:
  - `storefront.productDetail.reviews.like`: "Me gusta"
  - `storefront.productDetail.reviews.unlike`: "Quitar me gusta"
  - `storefront.productDetail.reviews.likesCount`: "{count} personas encontraron esto útil"
  - `storefront.productDetail.reviews.loginRequired`: "Inicia sesión para dar me gusta"

**Commit**: `feat(i18n): labels likes de reseñas`

**Verificación**:
- bun run typecheck → exit 0
- Manual: switch locale `en` → labels en inglés

---

## 3. Plan de ejecución (orden)

1. T1 — seeder reseñas (backend aislado, sin dependencias)
2. T2 — migration variant selectors (backend aislado)
3. T3 — seeder Fender con selectors (depende T2)
4. T4 — expandir reseñas mock (frontend aislado)
5. T5 — fix color verde (frontend aislado)
6. T6 — render condicional fit/size (frontend, depende T4 si requiere reseñas)
7. T7 — migration + model review likes (backend aislado)
8. T8 — API like/unlike (backend, depende T7)
9. T9 — botón like frontend (frontend, depende T8 o puede ser mock-first)
10. T10 — i18n labels (frontend, depende T9)

Cada task = 1 commit conventional. Push y PR quedan a tu criterio.

---

## 4. Riesgos

- **T1 reseñas**: si la BD no tiene customers existentes, el seeder falla. Mitigación: usar factory para crear customers ficticios o crear customers in-line en el seeder.
- **T2 migration**: agregar columnas con default true/false no rompe nada. `has_sizes: false` puede sorprender a otros tenants (Nike por ejemplo tiene tallas). Mitigación: cada seeder debe declarar explícitamente.
- **T6 condicional**: si otros productos sin `hasSizes` declarado quedan en `undefined`, `&&` lo trata como false → correcto. Sin embargo, type safety requiere que TODOS los mocks tengan los campos.
- **T8 auth**: si Sanctum no está configurado para customers (vs admin), el middleware falla. Verificar config antes.
- **T9 optimistic UI**: si el backend falla, el contador queda mal. Mitigación: rollback visual + toast de error.

---

## 5. Follow-ups deferred (no en este plan)

- Wire completo de reseñas al backend (actualmente mocks)
- Endpoint público GET /api/storefront/products/{slug}/reviews (paginación server-side)
- Review replies (admin responde a reseña)
- Review photos (upload)
- Review helpful votes positivos/negativos (separado de likes)
- Sort options (más recientes / más útiles)
- Wire `ProductDetailVariant` del frontend al backend (actualmente mock)