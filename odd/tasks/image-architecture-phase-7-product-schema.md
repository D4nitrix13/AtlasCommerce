<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD031 -->

# Plan: Fase 7 — Schema de productos rich (acabados, condición, tamaño, galería, exclusivo)

> **Status**: pendiente para próxima session. Audit completado y plan documentado.
>
> **Trigger**: el user pidió extender el schema de productos para soportar el modelo de negocio real de La Voz Instrumentos:
> 1. Acabados (1 producto puede tener N acabados, ej. stratocaster rojo/negro/azul)
> 2. Condición por acabado (nuevo / usado / ambos — ej. rojo=usado only, negro=nuevo only, azul=5 nuevo + 10 usado)
> 3. Tamaño opcional (ej. violín 4/4, no aplica para guitarras)
> 4. Bandera `is_exclusive` por producto
> 5. Galería de imágenes por producto (flexible en cantidad)
>
> **Bug CSS separado**: las cards de catálogo no están alineadas — la primera card se renderiza más arriba que las demás. Investigar en `frontend/src/components/storefront/products/ProductCard.astro`.

---

## 1. Audit del schema actual (commit actual `10d55e64` antes de Fase 7)

**Productos** (`backend/database/migrations/catalog/2026_06_22_032009_create_products_table.php`):
```
id, category_id, brand_id, currency_id,
name, slug, description, sku,
base_price_cents, cost_cents,
is_active, published_at,
timestamps, softDeletes
```

**Falta en productos**: `is_exclusive` (boolean para "Limited" / "Edición Limitada").

**Variantes** (`2026_06_22_032011_create_product_variants_table.php`):
```
id, product_id, name, sku, price_cents, cost_cents,
barcode, is_active, timestamps, softDeletes
```

**Falta en variantes**: `finish_slug`, `finish_label`, `finish_hex`, `condition` (enum), `stock`, `size_id` (FK).

**Imágenes** (`2026_06_22_032010_create_product_images_table.php`):
```
id, product_id, image_url, alt_text, is_primary, sort_order, timestamps
```

**Funciona bien** para galería por producto (1+ imágenes). Falta solo verificar si `image_url` apunta al storage key content-hash (Fase 2-6) o a un path absoluto.

**Sizes**: NO EXISTE. Hay que crear nueva tabla.

---

## 2. Modelo propuesto

### 2.1. Productos

Agregar columna `is_exclusive` (boolean default false):

```php
Schema::table('products', function (Blueprint $table): void {
    $table->boolean('is_exclusive')->default(false)->after('is_active');
});
```

### 2.2. Variantes

Agregar columnas a `product_variants`:

```php
Schema::table('product_variants', function (Blueprint $table): void {
    // Finish (per-product) — slug + label + hex
    $table->string('finish_slug', 64)->nullable()->after('name');
    $table->string('finish_label', 120)->nullable();
    $table->string('finish_hex', 9)->nullable(); // #RRGGBBAA
    
    // Condition (nuevo / usado / ambos)
    $table->enum('condition', ['nuevo', 'usado', 'ambos'])->default('nuevo')->after('finish_hex');
    
    // Stock per variant (per condition per finish)
    $table->unsignedInteger('stock')->default(0)->after('condition');
    
    // Size (FK a product_sizes, nullable porque no todos los productos tienen)
    $table->foreignId('size_id')->nullable()->after('stock')
        ->constrained('product_sizes')->nullOnDelete();
    
    // Index para queries comunes
    $table->index(['product_id', 'finish_slug', 'condition'], 'variants_lookup_idx');
});
```

### 2.3. Sizes (nueva tabla)

```php
Schema::create('product_sizes', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->string('slug', 64); // ej. '4-4', '3-4', '1-2'
    $table->string('label', 64); // ej. '4/4 (Full Size)', '3/4'
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
    $table->unique(['product_id', 'slug']);
    $table->index(['product_id', 'position']);
});
```

### 2.4. Migration de seeders existentes

Los seeders actuales (`FenderAmericanProfessionalTelecasterSeeder`, `GibsonModCollectionSeeder`, etc.) hardcodean variants con `sku` y `price_cents`. Hay que migrar para que cada variant declare su `finish_slug`, `finish_label`, `condition`, `stock`.

Para el ejemplo del user:
- Stratocaster rojo: 1 variant con `finish_slug='rojo'`, `condition='usado'`, `stock=3` (o el stock que tenga la tienda)
- Stratocaster negro: 1 variant con `finish_slug='negro'`, `condition='nuevo'`, `stock=1`
- Blu: 2 variants — uno nuevo stock=5, otro usado stock=10

Para "ambos" (cuando el mismo acabado se vende en ambas condiciones): 2 variants o 1 variant con `condition='ambos'` y `stock=total`. Recomendación: 2 variants separados (más granularidad para analytics + stock independiente).

### 2.5. StorageKey para imágenes

Confirmar que `product_images.image_url` apunta al storage key generado por `StorageKeyDeriver` (formato `tenants/{slug}/products/{slug}/{contentHash}.{ext}`) o a un path absoluto legacy.

Si apunta al path absoluto, refactorizar para usar el storage key consistente con Fase 2-6.

---

## 3. Frontend impact

### 3.1. Product interface

```typescript
export interface Product {
  id: number;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  compareAtPriceCents?: number;
  imageUrl: string;
  images?: readonly string[]; // Ya agregado en Fase 6
  category: string;
  stock: number; // Total cross-variants
  badges?: ProductBadge[];
  currency?: string;
  // Nuevos campos Fase 7:
  isExclusive?: boolean; // PDP muestra badge "Limitado" si true
  variants?: readonly ProductVariant[]; // Con finish + condition + size
  sizes?: readonly ProductSize[]; // Solo si el producto tiene tallas
}
```

### 3.2. PDP (Product Detail Page)

- Selector de **finish**: dropdown o grid de swatches (cada swatch es una foto miniatura del finish)
- Selector de **condition**: toggle (NUEVO / USADO / AMBOS, basado en qué listings existen para el finish seleccionado)
- Selector de **size** (opcional): si el producto tiene sizes, mostrar grid
- **Stock indicator**: mostrar stock disponible para el finish+condition+size seleccionado
- **Precio dinámico**: cambiar según el variant seleccionado
- **Badge "Edición Limitada"**: si `product.isExclusive`, mostrar badge

### 3.3. PDP de catálogo (grid)

- Mostrar "Edición Limitada" si `product.isExclusive`
- Mostrar rango de precios (min-max) si hay variants con precios distintos
- Mantener la imagen principal (la primera variant image)

### 3.4. ProductCard (grid)

- Mostrar badge "Limitado" si `product.isExclusive`
- Mantener layout actual (1 imagen + título + precio)

---

## 4. CSS bug separado: cards no alineadas

El user reporta "las cartas no tienen la misma posición la primera carta está más arriba que las demás" en la página de catálogo (PDP).

**Síntoma**: primera card del grid se renderiza desplazada hacia arriba vs las siguientes.

**Causa probable**:
- CSS grid con `align-items: start` que crea alturas desiguales cuando alguna card tiene badge exclusivo / condition badge que las demás no.
- O el wrapper `.grid` no usa `grid-auto-rows: 1fr` para forzar igual altura.

**Fix**:
```css
.products-grid {
    display: grid;
    grid-auto-rows: 1fr; /* todas las rows misma altura */
}
.product-card {
    height: 100%; /* estirar al row */
}
```

**Investigación**: revisar `frontend/src/components/storefront/products/ProductCard.astro` y el layout del catálogo donde se renderiza el grid.

---

## 5. Plan de ejecución

### Commit 1: Migration backend
- `2026_10_18_xxxxxx_alter_products_add_is_exclusive.php` — boolean default false
- `2026_10_18_xxxxxx_create_product_sizes_table.php` — nueva tabla
- `2026_10_18_xxxxxx_alter_product_variants_add_finish_condition_size_stock.php` — columnas finish_*, condition, stock, size_id

### Commit 2: Backend models
- `app/Models/Catalog/ProductSize.php` — nueva
- Update `ProductVariant` model — agregar fillable de finish_*, condition, stock, size_id
- Update `Product` model — agregar fillable de is_exclusive

### Commit 3: Seeders migración
- `GibsonModCollectionSeeder` — ya tiene variants con `isDefault`. Migrar a `finish_slug`/`condition`/`stock`.
- `FenderAmericanProfessional*Seeder` — similar.
- `ProductSeeder` (el JSON-driven) — actualizar JSON `products.json` con los nuevos campos. Factory debe respetar.

### Commit 4: Frontend types
- `frontend/src/types/catalog.ts` — agregar `isExclusive`, `variants`, `sizes` a Product.
- `frontend/src/types/product-detail-mock.ts` — agregar campos en `ProductDetailData`.
- `frontend/src/lib/product-mock-factory.ts` — populate `isExclusive`, `variants`, `sizes` desde spec.

### Commit 5: La-voz mock
- `frontend/src/data/la-voz-mock.ts` — `LaVozProduct` interface + adapter `laVozProductToProduct` con los nuevos campos.

### Commit 6: PDP updates
- `frontend/src/pages/[locale]/products/[slug].astro` — render selectors de finish/condition/size dinámicos.
- `frontend/src/components/storefront/products/ProductDetailVariants.astro` — UI de swatches + condition toggle.
- `frontend/src/components/storefront/products/ProductSizeGrid.astro` — UI de sizes.

### Commit 7: Catalog grid CSS fix
- `frontend/src/components/storefront/products/ProductCard.astro` — `h-full` para estirar a row height.
- Catálogo layout — `grid-auto-rows: 1fr` para igualar alturas.

### Commit 8: Tests
- Backend tests para variants con finish+condition+size.
- Frontend vitest para ProductMockSpec populate + ProductCard aspect-ratio fix.

---

## 6. Convenciones a aplicar (next session)

Convenciones cargadas en session actual:
- `.ai/rules/index.md`
- `.ai/rules/tooling/package-manager.md` (bun)
- `.ai/rules/tooling/docker-stack.md`
- `.ai/rules/workflow/conventions-first.md`
- `frontend/AGENTS.md`
- `backend/AGENTS.md`
- `docs/operations/tech-stack.md`
- `scripts/docker-stack.sh`

Reglas específicas para Fase 7:
- AGENTS.md backend: declarar `strict` types, PHP 8.5 named args, no `mixed` types, no codigo muerto
- AGENTS.md frontend: funciones <40 lineas, helpers en `src/lib/`, TypeScript strict
- Conventional commits: `feat(backend):` / `fix(frontend):` / `docs:` scopes
- No `git commit --amend`, no `--no-verify`, no commits con Co-authored-by de AI
- Cada commit work-unit: 1 feature del repo + tests + docs

---

## 7. Follow-ups del image architecture plan (acumulados)

De las 6 fases del image architecture plan original + Fase 6 (refactor assets) + Fase 7 (schema rich):
- [x] Fase 2 — StorageKeyDeriver + AssetController
- [x] Fase 3 — UrlSigner + cache config-driven
- [x] Fase 4 — Migration pattern
- [x] Fase 5 — backfill-images command
- [x] Fase 6 — assets/ consolidación
- [ ] **Fase 7 — Schema rich (acabados, condition, size, exclusive, gallery)** ← ESTA SESSION
- [ ] Fase 8 — flip strict/signing mode después de backfill masivo
- [ ] Backend: endpoints faltantes (favicon endpoint, wishlist endpoint) para que el frontend deje de mostrar 503s

---

## 8. Session actual cierre

Estado de la session:
- 12 commits merged en master cubriendo facets de producto y refactor de themes.
- Plan documentado para Fase 7 (schema rich).
- Bug CSS de cards separadas para investigar.
- Convenciones load-bearing creadas (`bun`, `docker-stack.sh`, `conventions-first`, `docker-stack.md`).
- Reglas persistidas en `.ai/rules/` para futuras sessions.

Próxima session:
1. Cargar convenciones primero (siempre).
2. Ejecutar Fase 7 según el plan arriba.
3. Investigar CSS bug de cards en paralelo.