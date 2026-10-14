# Equivalentes Nike de las 10 vistas — para review

> **Status**: documento de investigación para que revises antes de aprobar el siguiente sprint Nike. Las 8 vistas B2C tienen equivalente directo en nike.com. Las 2 vistas B2B (RFQ y Bulk) no porque Nike es B2C puro — se mantienen Bonfire editorial.

## Mapeo de vistas

| Vista AtlasCommerce | Equivalente nike.com | URL ejemplo |
|---|---|---|
| 03 · Account dashboard (cliente) | My Account / Order Status | nike.com/orders/details/ |
| 04 · Catálogo | Category pages | nike.com/w/mens-shoes-nik1zy7ok |
| 05 · Detalle producto | Product detail | (vía card → product page) |
| 08 · Carrito | Bag | nike.com/cart |
| 09 · Checkout | Checkout flow | nike.com/checkout |
| 10 · Mis órdenes | Order history | nike.com/orders/details/ |
| 01 · Login | Account login | nike.com/login (SPA) |
| 02 · Signup | Join us | nike.com/register |
| 06 · RFQ (B2B) | No aplica | nike.com no es B2B |
| 07 · Bulk order (B2B) | No aplica | nike.com no es B2B |

## Patrones visuales de Nike aplicables a las 8 vistas B2C

### Tipografía
- **Sans-serif bold** (Inter o system-ui)
- **Headlines gigantes** (text-5xl a text-8xl) en hero, contrastando con H1s de 36-48px en secciones secundarias
- **Tracking apretado** (`tracking-tight`)
- **Jerarquía clara**: H1 mega, H2 grande, H3 mediano, body regular

### Layout
- **Full-bleed heroes** (imagen ocupa 100% del viewport width)
- **Grid limpio** de 2 a 4 columnas según viewport
- **Whitespaces generosos** (py-16 a py-24 entre secciones)
- **Cards rectangulares** (rounded-none o rounded-sm, NO rounded-2xl)
- **Sin decorativos orgánicos** (cero squiggles, cero blobs, cero sunbursts)
- **Accent color** solo en elementos puntuales (volt yellow, orange)

### Componentes característicos
- **Mega menu nav** (dropdown con 2-3 columnas de subcategorías)
- **Hero con imagen de atleta** + headline bold + Shop CTA
- **Product card minimalista**: imagen + nombre + precio + tallas color swatches
- **Carousels horizontales** de 4-5 productos visibles
- **Tabs de tallas** inline en product detail
- **Sticky Add to Cart** bar en mobile
- **Filter pills** redondeadas completas
- **Search bar** prominente con mega menu de sugerencias

### Tokens a aplicar en AtlasCommerce

| Token Nike | Valor | Equivalente Tailwind |
|---|---|---|
| `--nike-white` | `#FFFFFF` | `bg-white` |
| `--nike-black` | `#111111` | `bg-black` o `bg-slate-900` |
| `--nike-gray-100` | `#F5F5F5` | `bg-slate-50` |
| `--nike-gray-200` | `#E5E5E5` | `border-slate-200` |
| `--nike-gray-400` | `#757575` | `text-slate-500` |
| `--nike-gray-600` | `#333333` | `text-slate-700` |
| `--nike-volt` | `#CEFF00` | `bg-lime-400` (highlight) |
| `--nike-orange` | `#FA5400` | `bg-orange-500` (badges) |

## Diferencias clave entre AtlasCommerce actual (Bonfire) y Nike

| Aspecto | Bonfire (sprint actual) | Nike (siguiente sprint) |
|---|---|---|
| Background home | `bg-slate-50` cream-ish | `bg-white` puro |
| Cards | `rounded-2xl` con shadow azul-fría | `rounded-none` con border sutil |
| Decorativos | Squiggle, Blob, sunbursts | Ninguno |
| Hero mockups | 3 ProductMockup flotando | Imagen full-bleed con modelo |
| Tipografía | Inter jerarquía moderada | Inter bold prominente |
| CTAs | `rounded-lg` con bg primary | `rounded-none` con bg black |
| Spacing | Conservador | Generoso (py-20 a py-32) |
| Color accent | `var(--primary)` tenant | `var(--primary)` tenant + volt/orange highlights |
| Tenant-aware | Sí | Igual |

## Comparación por vista

### 03 · Account dashboard (cliente)
- **Bonfire actual**: KPI cards en grid, QuickActionsBar con 5 pills, secciones de snapshots
- **Nike equivalente**: My Account minimalista con header de miembro (member since, puntos), lista de órdenes recientes con thumbnails, y un "Recently viewed" carousel
- **Diferencia clave**: Nike pone más énfasis en el member status + loyalty. AtlasCommerce debería replicar con un header de miembro + un grid más espaciado

### 04 · Catálogo
- **Bonfire actual**: grid de 2-4 cols con sidebar de filtros a la izquierda
- **Nike equivalente**: category page con hero pequeño de breadcrumb + filtros como pills arriba + grid de 4 cols sin sidebar
- **Diferencia clave**: Nike usa filter PIZZA HORIZONTAL, no sidebar vertical. Filtros más accesibles en mobile

### 05 · Detalle producto
- **Bonfire actual**: gallery 2-col + info 2-col con variant pills, qty stepper, related products
- **Nike equivalente**: gallery full-width con imagen dominante + info sticky al lado, tabs de tallas/colores, sticky ATC bar en mobile, recomendaciones horizontales abajo
- **Diferencia clave**: Nike prioriza la imagen del producto, no el spec sheet. Menos info técnica visible por default

### 08 · Carrito
- **Bonfire actual**: list 2-col con items + summary sticky
- **Nike equivalente**: similar pero más minimalista, sin iconos de mockups, solo imagen + título + precio + qty
- **Diferencia clave**: Nike es más minimalista, sin badges ni extras. Recomendaciones de cross-sell al fondo

### 09 · Checkout
- **Bonfire actual**: stepper 3 pasos (Envío, Pago, Revisión) con form 2-col + summary sticky
- **Nike equivalente**: similar pero más linear, menos decorativos, con Apple Pay / Google Pay buttons prominentes
- **Diferencia clave**: Nike prioriza un stepper visible siempre arriba con progress clara

### 10 · Mis órdenes
- **Bonfire actual**: cards colapsables con details (mockup + items + actions)
- **Nike equivalente**: lista vertical simple de órdenes con order #, status, fecha, total + expand para ver items
- **Diferencia clave**: Nike es más simple, menos detallado, más "order summary" que "order detail"

### 01 · Login
- **Bonfire actual**: form con email + password + Google OAuth buttons
- **Nike equivalente**: account lookup con single input (email o member ID), detecta método de auth
- **Diferencia clave**: Nike no muestra password en login inicial. Hace lookup primero y luego password

### 02 · Signup
- **Bonfire actual**: form multi-step con email + password + name + verify
- **Nike equivalente**: minimalista, single step con email + name + password + birth date
- **Diferencia clave**: Nike prioriza speed-to-account. Menos friction en signup

## Recomendación

**Las 8 vistas B2C deberían seguir Nike aesthetic.** Las 2 vistas B2B (RFQ y Bulk) y todas las admin views mantienen Bonfire editorial.

## Plan de implementación

1. ✅ Prompt v2 con Nike inspiration ya está creado (`odd/tasks/atlascommerce-design-prompt-v2.md`)
2. ✅ Design system Nike ya está documentado (`docs/frontend/design-references-nike.md`)
3. ✅ Categorización de prompts ya está en `odd/tasks/atlascommerce-prompts-plan.md`
4. **Pendiente**: pasas el prompt v2 a la IA externa (la misma que hizo el sprint Bonfire)
5. **Pendiente**: devuelves ZIP con mockups Nike-style para las 8 vistas B2C
6. **Pendiente**: yo ejecuto la implementación con el patrón 1 vista = 1 PR
7. **Pendiente**: notifico al usuario para merge

## Siguiente paso

¿Apruebas este mapeo y procedo con el sprint Nike? Si sí, pasale el prompt v2 a la IA externa y devuélveme el ZIP. Si quieres ajustes al mapeo o al prompt, los hago antes de mandar a la IA.
