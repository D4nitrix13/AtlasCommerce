# Plan: Fase 8 — Extended Product Content (descripción extendida, highlights, specs agrupadas, FAQ)

> **Status**: en ejecución.
>
> **Trigger**: el user pidió extender el modelo de productos con cuatro
> tipos de contenido adicional que se renderiza en la PDP: descripción
> extendida con título, highlights (badges con icono + label), specs
> agrupadas por categoría (Cuerpo, Mástil, Electrónica, Hardware,
> Miscelánea) y FAQ (preguntas frecuentes con respuesta). El objetivo
> es que los componentes PDP dejen de tener texto hardcoded y pidan
> la información a la API.

---

## 1. Audit del estado actual

### Componentes frontend que ya existen

- `frontend/src/components/storefront/products/ProductHighlights.astro` + `.types.ts` + `.stories.ts`. Renderiza un grid de items con icono + label.
- `frontend/src/components/storefront/products/ProductSpecificationsByCategory.astro` + `.types.ts` + `.stories.ts`. Renderiza accordions agrupados por categoría.
- `frontend/src/components/storefront/products/ProductDetailsBlock.astro`. Renderiza descripción con toggle "Leer más". Ya soporta `intro` largo.
- `frontend/src/components/storefront/FaqAccordion.astro` + `.stories.ts`. Genérico (no atado a producto). Lo reusamos con un wrapper que lo ata al producto.

### Types frontend que ya existen

- `ProductHighlightsItem` con keys de icono en español: `'premio' | 'destello' | 'regla' | 'escudo' | 'estrella' | 'musica'`.
- `ProductSpecificationsByCategoryGroup` con `category: string` e `items: { label, value }[]`.
- `ProductDetailData.highlights?` y `ProductDetailData.groupedSpecs?` ya en el shape (mock).

### Backend que NO existe

- Tablas para `highlights`, `grouped_specs`, `faqs`, `extended_descriptions`.
- Models para los 4 recursos.
- Seeders con contenido demo (la Les Paul Supreme ya tiene el contenido en español provisto por el user).
- Endpoint storefront que devuelva los 4 recursos anidados al product detail.

### Lo que la PDP ya hace

La PDP renderiza `detail.highlights` y `detail.groupedSpecs` con checks `!== undefined`. Solo falta que la API los provea. Falta agregar la sección de FAQ y la descripción extendida con título separado.

---

## 2. Modelo propuesto

### 2.1. Tabla `product_highlights`

```php
Schema::create('product_highlights', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('icon', 32); // 'premio' | 'destello' | 'regla' | etc.
    $table->string('label', 200);
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
    $table->index(['product_id', 'position']);
});
```

### 2.2. Tabla `product_grouped_specs` + `product_spec_values`

Dos tablas porque una spec tiene un grupo (Cuerpo, Mástil, etc.) y N valores (label, value).

```php
Schema::create('product_grouped_specs', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('category', 64);
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
    $table->index(['product_id', 'position']);
});

Schema::create('product_spec_values', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('grouped_spec_id')->constrained('product_grouped_specs')->cascadeOnDelete();
    $table->string('label', 120);
    $table->text('value');
    $table->unsignedInteger('position')->default(0);
    $table->index(['grouped_spec_id', 'position']);
});
```

### 2.3. Tabla `product_faqs`

```php
Schema::create('product_faqs', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('question', 300);
    $table->text('answer');
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
    $table->index(['product_id', 'position']);
});
```

### 2.4. Tabla `product_extended_descriptions`

```php
Schema::create('product_extended_descriptions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('title', 200);
    $table->text('body');
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
    $table->index(['product_id', 'position']);
});
```

---

## 3. Models

- `App\Models\Catalog\ProductHighlight`
- `App\Models\Catalog\ProductGroupedSpec` (con relación `values()` hasMany)
- `App\Models\Catalog\ProductSpecValue`
- `App\Models\Catalog\ProductFaq`
- `App\Models\Catalog\ProductExtendedDescription`

Cada uno con relación inversa en `Product` (hasMany).

---

## 4. Seeders

Extender `ProductSeeder` para que pueble las 4 tablas a partir de campos adicionales en `products.json`. Demo data: la Les Paul Supreme con su título "The Les Paul, llevada al extremo" y el body en español provisto por el user. Highlights, specs y FAQs derivadas del ejemplo provisto.

---

## 5. API endpoint

Extender `App\Http\Controllers\Api\Storefront\ProductController::show` y `App\Http\Resources\Catalog\ProductResource` para incluir:

```json
{
    "data": {
        "id": ...,
        "name": ...,
        ...
        "extendedDescriptions": [
            { "title": "The Les Paul, llevada al extremo", "body": "..." }
        ],
        "highlights": [
            { "icon": "premio", "label": "Cuerpo de caoba" }
        ],
        "groupedSpecs": [
            {
                "category": "Cuerpo",
                "items": [
                    { "label": "Madera", "value": "Caoba con tapa de arce" }
                ]
            }
        ],
        "faqs": [
            { "question": "...", "answer": "..." }
        ]
    }
}
```

---

## 6. Frontend

### 6.1. Componente `ProductExtendedDescription`

Recibe un array de `{ title, body }` y renderiza la primera descripción extendida con título bold + body. Si hay más de una, renderiza un details/summary por cada una.

### 6.2. Componente `ProductFAQ`

Recibe `faqs: { question, answer }[]` y reusa `FaqAccordion.astro` con shape `{ question, answer }`. Renderiza la sección "Frequently Asked Questions" con icono de guitarra + lista de accordion.

### 6.3. Wirear en `pages/[locale]/products/[slug].astro`

Después del `ProductDetailsBlock` actual, agregar:
- `ProductExtendedDescription` con `detail.extendedDescriptions`
- `ProductHighlights` con `detail.highlights`
- `ProductSpecificationsByCategory` con `detail.groupedSpecs`
- `ProductFAQ` con `detail.faqs`

Cada uno con check `!== undefined` para que productos sin el contenido no rompan el render.

---

## 7. Convenciones

- AGENTS.md backend: `declare(strict_types=1);`, PHP 8.4+, named args, no `mixed` types.
- AGENTS.md frontend: componentes < 250 líneas, TypeScript strict, Tailwind para styling.
- Conventional commits: español, prosa plana 72 cols, scope `backend`/`frontend`/`test`.
- Branch: `feat/extended-product-content`.
- No Co-Authored-By de AI.
- Cada commit = un work-unit, tests incluidos.

---

## 8. Plan de commits

1. `feat(backend): migrations para product_highlights y product_grouped_specs + spec_values` (Fase 8)
2. `feat(backend): migrations para product_faqs y product_extended_descriptions` (Fase 8)
3. `feat(backend): models para highlights, grouped specs, faqs y extended descriptions` (Fase 8)
4. `feat(backend): seeders pueblan contenido extended desde products.json` (Fase 8)
5. `feat(backend): storefront API expone extended descriptions, highlights, grouped specs y faqs` (Fase 8)
6. `test(backend): tests para los 4 recursos nuevos y la API` (Fase 8)
7. `feat(frontend): ProductFAQ component con accordion data-driven` (Fase 8)
8. `feat(frontend): wirear 4 secciones extended en la PDP` (Fase 8)
9. `test(frontend): tests para los componentes y wiring` (Fase 8)

---

## 9. Demo data (la Les Paul Supreme)

El user proveyó este contenido en español para usar como demo:

- **Título descripción extendida**: "The Les Paul, llevada al extremo"
- **Body**: el párrafo completo sobre el regreso de la Les Paul Supreme con Burstbucker Pro, Burstbucker Pro Plus, hardware dorado, etc.
- **Highlights**:
  - Cuerpo de caoba
  - Tapa de arce
  - Mástil de caoba con perfil SlimTaper
  - Diapasón de ébano de radio compuesto con 22 trastes medio jumbo
  - Inlays de madreperla en la pala y el diapasón
  - Pastillas humbucker Burstbucker Pro y Burstbucker Pro Plus
  - Controles push/pull para la división de bobinas, el cambio de fase y la derivación pura
  - Estuche rígido Gibson
- **Specs agrupadas**:
  - Cuerpo: Madera (Caoba), Tapa (Arce AAA)
  - Mástil: Madera (Caoba), Perfil (SlimTaper), Diapasón (Ébano), Trastes (22 medium jumbo), Inlays (Madreperla)
  - Electrónica: Pastillas (Burstbucker Pro + Burstbucker Pro Plus)
  - Hardware: Herrajes (Dorado)
  - Miscelánea: Estuche (Rígido)
- **FAQ** (placeholder por ahora, hasta que el user provea): se omite si no hay data.

---

## 10. Follow-ups

- Endpoint admin para gestionar el contenido (CRUD de las 4 tablas).
- Wirear la API real cuando el backend de storefront exista (hoy el frontend consume mock data).
- Agregar FAQ data al seed del La Voz.