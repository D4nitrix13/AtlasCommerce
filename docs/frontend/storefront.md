# Tienda Pública — Storefront Multi-Tienda

## Visión general

El storefront es la tienda pública que ven los clientes. Un
mismo build de `Astro` sirve Panda Estampados, Kitsune Store,
MM Boutique y Morales Store. La tienda actual se resuelve a
partir del dominio de origen (`X-Forwarded-Host`) y se
refuerza en el backend vía el endpoint
`GET /api/storefront/theme` (ver
[`architecture/flow.md`](../architecture/flow.md
"architecture/flow.md") § 2).

## Resolución de tienda por dominio

```txt
panda-estampados.com → Store{id: 1, name: "Panda Estampados"}
kitsune.com           → Store{id: 2, name: "Kitsune Store"}
mm-boutique.com       → Store{id: 3, name: "MM Boutique"}
morales-store.com     → Store{id: 4, name: "Morales Store"}
```

Las dos primeras marcas conviven en la instancia
`panda-estampados-and-kitsune`. Las otras dos viven en
instancias single-tenant.

## Mecanismo de resolución

### Theme loader dinámico (`frontend/src/middleware.ts`)

Implementado en commit `cd848b1` y posteriores:

- Middleware Astro lee `request.headers.host`.
- Llama a `GET /api/storefront/theme` con timeout 1s.
- Inyecta el resultado en `Astro.locals.theme`.
- Fallback a `DEFAULT_THEME` si el backend no responde
  (cero páginas rotas en outage).

### Fallback estático (`frontend/src/config/stores.ts`)

Se mantiene como fallback cuando el theme dinámico viene
incompleto. `STORE_BRANDS` reexporta los paquetes npm del
workspace `packages/themes/` (Panda, Kitsune, MM Boutique,
Morales Store). NO es la fuente principal.

## Páginas del storefront

Todas las rutas viven bajo `[locale]/` (es/en).

### Homepage

```txt
/[locale] → index.astro
```

- Hero banner por tenant
- Productos destacados (filtrados por tienda)
- Categorías
- Promociones activas

### Catálogo

```txt
/[locale]/products               → products/index.astro
/[locale]/products/[slug]        → products/[slug].astro
```

- Grilla de productos con filtros
- Detalle de producto con galería, precio, variantes
- Productos visibles en la tienda actual
  (`product_stores.visible = true`)

### Búsqueda

```txt
/[locale]/search → search/index.astro
```

- Resultados paginados
- Filtros por categoría, marca, precio

### Carrito

```txt
/[locale]/cart → cart.astro
```

- Listado de items
- Modificar cantidad
- Eliminar items
- Resumen con totales
- CTA a checkout

### Checkout multi-step (F4)

```txt
/[locale]/checkout → checkout.astro
```

- Stepper visual: dirección → envío → pago → revisión.
- Persistencia en `localStorage` para refresh-safe.
- Cálculo de totales siempre desde el backend.

### Pedidos del cliente

Ver [`account/orders/[id].astro`](../frontend/README.md
"frontend/README.md") — sección `/account`.

### B2B — Panda Estampados

```txt
/[locale]/rfq                  → rfq/index.astro
/[locale]/rfq/confirmation     → rfq/confirmation.astro
/[locale]/bulk-order           → bulk-order/index.astro
```

- RFQ — Request For Quote multi-step (`RFQStepper.astro`).
- Bulk pricing — tabla de precios por volumen
  (`VolumePricingTable.astro` + `BulkPricingCalculator.tsx`).
- Multi-sucursal — direcciones por empresa
  (`AddressesManager.tsx`).

## Branding por tienda

### Logo

Cada tienda tiene su propio logo. El storefront carga el
logo según la tienda actual desde `store_themes`.

### Colores

Los colores primarios se aplican via `store_themes` y se
exponen como CSS variables. Cada tenant define:

```css
:root {
  /* Panda: esmeralda */
  --primary: #059669;
  --secondary: #10b981;
  --accent:  #f59e0b;
}

/* Kitsune: violeta */
:root {
  --primary: #7c3aed;
  --secondary: #8b5cf6;
  --accent:  #f472b6;
}
```

### Redes sociales

`store_social_links` por tienda (Instagram, Facebook,
WhatsApp, TikTok). Se muestran en el header y footer.

### WhatsApp

Cada tienda tiene su propio número de WhatsApp para
contacto directo. Configurado vía `store_themes.whatsapp`.

## SEO por tienda

Cada tienda tiene su propio SEO: `<title>`, `description`,
`og:title`, `og:image`, `og:locale`. Configurado desde
`store_themes` y `site.ts`.

## Componentes del storefront

```txt
src/components/storefront/
├── auth/                  # login/signup compartidos
├── b2b/                   # RFQ + bulk + multi-sucursal (Panda)
├── branding/              # logo + cover por tenant
├── cart/
│   ├── CartItem.astro
│   ├── CartSummary.astro
│   └── CartEmpty.astro
├── checkout/
│   ├── CheckoutStepper.astro
│   ├── AddressStep.astro
│   ├── ShippingStep.astro
│   ├── PaymentStep.astro
│   └── ReviewStep.astro
├── footer/
│   └── PublicFooter.astro
├── header/
│   └── PublicHeader.astro
├── home/
│   └── HeroBanner.astro
├── layout/
├── products/
│   ├── ProductCard.astro
│   ├── ProductGrid.astro
│   ├── ProductGallery.astro
│   ├── ProductInfo.astro
│   ├── ProductVariants.astro
│   ├── ProductDescription.astro
│   ├── ProductRelated.astro
│   ├── ProductReviews.astro
│   ├── ProductQuantitySelector.astro
│   └── WishlistButton.astro
└── search/
```

Cada componente nuevo trae su `*.stories.ts` (ver
[storybook.md](storybook.md "frontend/storybook.md")).

## Endpoints del storefront

```txt
GET    /api/storefront/theme              # theme del tenant (sin auth)
GET    /api/storefront/products           # catálogo público
GET    /api/storefront/products/{slug}    # detalle producto
GET    /api/storefront/promotions         # promociones activas
GET    /api/assets/{path}                 # assets públicos
GET    /api/tracking/{tracking_code}      # tracking de pedido
GET    /api/products/{slug}/price?quantity=N  # wholesale pricing
```

Los controladores viven en
[`backend/app/Http/Controllers/Api/Storefront/`](../../backend/app/Http/Controllers/Api/Storefront/
"backend/app/Http/Controllers/Api/Storefront/").
