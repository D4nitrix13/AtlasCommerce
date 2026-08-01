# Tienda Pública — Storefront Multi-Tienda

## Visión general

El storefront es la tienda pública que ven los clientes. Un
mismo build de `Astro` sirve tanto Panda Estampados como
Kitsune Store. La tienda actual se resuelve a partir del
dominio de origen.

## Resolución de tienda por dominio

```txt
pandas-estampados.com → Store{id: 1, name: "Panda Estampados"}
kitsune.com           → Store{id: 2, name: "Kitsune Store"}
```

El frontend necesita un mecanismo para:

1. Identificar la tienda actual desde el dominio.
2. Cargar configuración de la tienda (logo, colores, redes
   sociales).
3. Filtrar productos y contenido por tienda.
4. Aplicar estilos/branding por tienda.

## Mecanismo de resolución

### Opción 1: Configuración estática

Un archivo `src/config/stores.ts` mapea dominios a
configuración de tienda:

```ts
// src/config/stores.ts (conceptual)

export const stores = {
    "pandas-estampados.com": {
        id: 1,
        name: "Panda Estampados",
        slug: "panda-estampados",
        logo: "/images/panda-logo.svg",
        primaryColor: "#059669",
        secondaryColor: "#10b981",
        whatsapp: "+505 8888 1234",
    },
    "kitsune.com": {
        id: 2,
        name: "Kitsune Store",
        slug: "kitsune",
        logo: "/images/kitsune-logo.svg",
        primaryColor: "#7c3aed",
        secondaryColor: "#8b5cf6",
        whatsapp: "+505 8888 5678",
    },
} as const;

export type StoreId = keyof typeof stores;
```

### Opción 2: Resolución dinámica (futuro)

Un helper `src/lib/store-context.ts` resuelve la tienda
desde el dominio:

```ts
// src/lib/store-context.ts (conceptual)

export function resolveStoreFromDomain(
    hostname: string
): StoreConfig {
    const store = stores[hostname as StoreId];

    if (!store) {
        throw new Error(
            `No store configured for domain: ${hostname}`
        );
    }

    return store;
}
```

En `Astro`, se puede usar en el frontmatter:

```astro
---
import { resolveStoreFromDomain } from "../lib/store-context";

const store = resolveStoreFromDomain(Astro.url.hostname);
---

<html>
<head>
    <title>{store.name}</title>
    <style define:vars={{ primaryColor: store.primaryColor }}>
        :root { --primary: var(--primaryColor); }
    </style>
</head>
<body>
    <img src={store.logo} alt={store.name} />
    <slot />
</body>
</html>
```

## Páginas del storefront

### Homepage

```txt
/ → index.astro
```

- Banner principal
- Productos destacados (filtrados por tienda)
- Categorías
- Promociones activas

### Catálogo

```txt
/products → products/index.astro
/products/:slug → products/[slug].astro
```

- Grilla de productos con filtros
- Detalle de producto con imágenes, precio, variantes
- Productos visibles en la tienda actual
  (`product_stores.visible = true`)

### Categorías

```txt
/categories → categories/index.astro
```

- Listado de categorías
- Productos por categoría

### Carrito

```txt
/cart → cart.astro
```

- Listado de items
- Modificar cantidad
- Eliminar items
- Resumen con totales

### Checkout

```txt
/checkout → checkout.astro
```

- Dirección de envío
- Método de pago
- Confirmación de pedido

### Pedidos del cliente

```txt
/orders → orders/index.astro
```

- Historial de pedidos
- Detalle de pedido
- Estado del envío

## Branding por tienda

### Logo

Cada tienda tiene su propio logo. El storefront carga el
logo según la tienda actual.

### Colores

Cada tienda define colores primario y secundario. Se
aplican como variables CSS:

```css
:root {
    --primary: #059669;    /* Panda: esmeralda */
    --secondary: #10b981;
}

/* o */
:root {
    --primary: #7c3aed;    /* Kitsune: violeta */
    --secondary: #8b5cf6;
}
```

### Redes sociales

Cada tienda tiene sus propias redes sociales (Instagram,
Facebook, WhatsApp, TikTok). Se muestran en el header y
footer.

### WhatsApp

Cada tienda tiene su propio número de WhatsApp para
contacto directo.

## SEO por tienda

Cada tienda debe tener su propio SEO:

```astro
---
const store = resolveStoreFromDomain(Astro.url.hostname);
---

<head>
    <title>{store.name} - Productos personalizados</title>
    <meta name="description" content={store.description} />
    <meta property="og:title" content={store.name} />
    <meta property="og:image" content={store.logo} />
</head>
```

## Componentes del storefront

```txt
src/components/storefront/
├── header/
│   └── PublicHeader.astro       # Header con logo, nav
├── footer/
│   └── PublicFooter.astro       # Footer con redes sociales
├── products/
│   ├── ProductCard.astro        # Tarjeta de producto
│   └── ProductGrid.astro        # Grilla de productos
└── home/                        # (pendiente)
```

## Datos mock actuales

El storefront actualmente usa datos hardcodeados en
`src/data/admin-dashboard.ts`. Conectado a la API, estos
datos deben reemplazarse con llamadas a:

```txt
GET /api/storefront/products?store_id={id}
GET /api/storefront/categories?store_id={id}
GET /api/storefront/promotions?store_id={id}
```

**Nota:** Los endpoints del storefront aún no están
implementados. Los controladores en `Api/Storefront/`
están vacíos (`.gitkeep`).

## Pendiente

- `src/lib/store-context.ts` — No creado
- `src/config/stores.ts` — No creado
- Endpoints storefront en backend — No implementados
- Conexión con API real — No implementada
- SEO por tienda — No implementado
- Redes sociales por tienda — No implementado
- WhatsApp por tienda — No implementado
- Branding dinámico — No implementado
