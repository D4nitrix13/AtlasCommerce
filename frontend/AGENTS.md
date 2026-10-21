<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

# Frontend Rules — Kitsune Store

**Stack:** Astro 7 + React 19 + Tailwind CSS 4 + TypeScript 6 + Zod 4 + react-hook-form + Chart.js 4

> "Este código lo va a leer otra persona cansada, con prisa y sin contexto. ¿Lo entenderá rápido?"

> **Este archivo es la convención específica del frontend.** Para los principios
> transversales (SOLID, DRY, KISS, tests, seguridad, workflow de PR), ver
> [`/CONTRIBUTING.md`](../CONTRIBUTING.md) en la raíz del repo. Las reglas de
> este archivo **no contradicen** las de CONTRIBUTING.md — las complementan y
> detallan. Si hay conflicto, manda CONTRIBUTING.md.

> **Reglas transversales del proyecto.** Este archivo cubre lo
> específico del frontend. Las reglas load-bearing que aplican a varios
> scopes viven en
> [`.ai/rules/index.md`](../.ai/rules/index.md). Antes de editar
> CUALQUIER archivo del frontend, abrí ese índice y leé las reglas
> cuyos globs cubran los paths en scope. Si una regla contradice este
> archivo, gana este archivo; si contradice `CONTRIBUTING.md`, gana
> `CONTRIBUTING.md`.

---

## 1. Tamaño de archivos

| Tipo de archivo    | Líneas máximas |
| ------------------ | -------------- |
| Componente simple  | 150            |
| Componente mediano | 250            |
| Página completa    | 400            |
| Función            | 40             |
| Constantes / types | sin límite     |
| `*.stories.ts`     | sin límite (separar variantes grandes) |

Si supera **300-400 líneas**, dividir. La página es composición, no un monolito.

---

## 2. Nomenclatura

### Componentes → PascalCase

ProductCard.astro, AdminSidebar.astro, RecentOrdersTable.astro

Evitar: Card.astro, Component1.astro, NewComponent.astro.

### Variables y funciones → camelCase

```ts
const totalSales = 1500;
function formatCurrency(value: number) { ... }
```

Evitar: TotalSales, total_sales, x.

### Constantes globales → UPPER_SNAKE_CASE

```ts
const API_BASE_URL = "http://localhost:9000/api";
const DEFAULT_PAGE_SIZE = 10;
```

### Booleanos → sonar como pregunta

```ts
const isOpen = true;
const hasProducts = products.length > 0;
const canEditOrder = user.role === "admin";
```

Evitar: open, productsStatus, edit.

### Funciones de eventos → handle + verbo

```ts
function handleSubmit() {}
function handleDeleteProduct() {}
```

Props de React: onClick, onSubmit, onDelete.

---

## 3. Retorno anticipado

Evitar anidación profunda. Evaluar condiciones negativas primero:

```ts
// Mal
if (user) {
  if (user.role === "admin") {
    if (products.length > 0) { ... }
  }
}

// Bien
if (!user) return;
if (user.role !== "admin") return;
if (products.length === 0) return;
// lógica
```

Máximo **2-3 niveles de anidación**.

---

## 4. Un solo responsabilidad

Cada componente responde **una pregunta**. Si la respuesta tiene múltiples partes, dividir.

```txt
// Mal: dashboard.astro contiene sidebar, header, cards, tabla, gráficos, modal, fetchs

// Bien
admin/index.astro          → composición
components/admin/dashboard/
  MetricCard.astro
  RevenueChart.astro
  RecentOrdersTable.astro
  TrafficSourcesCard.astro
```

---

## 5. Componentes reutilizables

Si se repite **3+ veces** la misma estructura → extraer componente.

```astro
<!-- En vez de repetir esto: -->
<div class="rounded-xl bg-white p-6 shadow-sm">
  <p class="text-sm text-gray-500">{title}</p>
  <h3 class="text-2xl font-bold">{value}</h3>
</div>

<!-- Crear -->
<StatCard title="Ventas" value="$2,500" />
<StatCard title="Pedidos" value="128" />
```

**DRY con criterio:** repetir una vez está bien. Repetir tres veces ya es candidato a abstracción.

---

## 6. Separación UI vs Negocio

### UI genérico → `src/components/ui/`

13 UI primitives sin lógica de negocio. Cada una tiene su
`*.stories.ts` en Storybook 10:

```txt
Alert   Badge   Breadcrumb  Button  Card  Dropdown
Icon    Input   Modal       Pagination  Select  Table  Textarea
```

Más extensiones React para casos interactivos:

```txt
LanguageSwitcher.tsx       # selector de locale es / en
PasswordInput.tsx          # input password con toggle visibility
PasswordStrengthMeter.tsx  # medidor de fortaleza zxcvbn-style
SecurityChecklist.tsx      # checklist 2FA / recovery codes
SocialIcons.tsx            # iconos de redes sociales
```

### Componentes de dominio → `src/components/{domain}/`

```txt
storefront/                # tienda pública
├── auth/                  # login/signup compartidos
├── b2b/                   # RFQ + bulk + multi-sucursal (Panda)
├── branding/              # logo + cover por tenant
├── cart/                  # items + summary + empty state
├── checkout/              # checkout multi-step
├── footer/
├── header/
├── home/                  # HeroBanner
├── layout/
├── products/              # ProductCard, ProductGrid, ProductGallery
└── search/

account/                   # sección clientes
├── addresses/             # AddressesManager + multi-sucursal
├── dashboard/             # QuickActionCard, WelcomeBanner, Orders
├── layout/
├── orders/                # OrdersTable, OrderTimeline, StatusFilterPills
└── wishlist/              # WishlistGrid + WishlistItem

admin/                     # panel administrativo
├── auth/  catalog/  customers/  dashboard/  header/
├── inventory/  marketing/  reports/  returns/  sales/
├── settings/  shipments/  sidebar/  stores/  support/
```

Regla: los componentes de UI en `ui/` **no** deben conocer reglas
específicas del negocio. Si un componente necesita lógica de
negocio, vive en `components/{domain}/`.

---

## 7. Nombres que expliquen intención

```ts
// Mal
const d = products.filter(p => p.s > 0);
function calc(x) { return x * 1.15; }

// Bien
const availableProducts = products.filter(product => product.stock > 0);
function calculatePriceWithTax(price: number) { return price * 1.15; }
```

No tener miedo de nombres largos si son claros.

---

## 8. Orden interno de componentes Astro

```astro
---
// 1. Imports
import StatCard from "./StatCard.astro";

// 2. Types/Interfaces
interface Props {
  user: User;
}

// 3. Props
const { user } = Astro.props;

// 4. Constantes
const stats = [ ... ];

// 5. Funciones auxiliares
// 6. Datos calculados
const hasStats = stats.length > 0;
---

<!-- 7. HTML -->
<section>
  {hasStats ? (
    <div class="grid gap-4 md:grid-cols-3">
      {stats.map((stat) => <StatCard {...stat} />)}
    </div>
  ) : (
    <p>No hay estadísticas disponibles.</p>
  )}
</section>
```

---

## 9. Tailwind CSS — orden de clases

Ordenar por intención:

```txt
1. layout:       flex, grid, block, hidden
2. posición:     relative, absolute, top, left
3. tamaño:       w, h, min, max
4. espaciado:    m, p, gap
5. bordes:       rounded, border
6. fondo:        bg
7. texto:        text, font
8. efectos:      shadow, hover, transition
9. responsive:   sm, md, lg
```

```html
<!-- Mal -->
<div class="text-white p-4 flex rounded-xl bg-blue-500 justify-between shadow-md items-center mt-4 hover:bg-blue-600">

<!-- Bien -->
<div class="mt-4 flex items-center justify-between rounded-xl bg-blue-500 p-4 text-white shadow-md hover:bg-blue-600">
```

---

## 10. Frontend ≠ Backend

El frontend se encarga de:

- Mostrar datos
- Enviar formularios
- Validaciones básicas (Zod en cliente)
- Estados visuales (loading, error, empty)
- Navegación

Laravel se encarga de:

- Validaciones reales y permisos
- Queries a BD, cálculo de totales, stock
- Autenticación, seguridad, pagos

**El cálculo confiable siempre viene del backend.**

---

## 11. Estados de interfaz (obligatorio)

Todo componente con datos debe manejar:

```astro
{isLoading && <p>Cargando productos...</p>}

{hasError && (
  <p class="text-red-500">No se pudieron cargar los productos.</p>
)}

{!isLoading && products.length === 0 && (
  <p>No hay productos registrados.</p>
)}

{products.length > 0 && (
  <ProductTable products={products} />
)}
```

Estados mínimos: **loading · empty · error · success · disabled**.

---

## 12. Validar datos antes de mostrar

Nunca asumir que todo viene perfecto del backend:

```ts
const productName = product.name ?? "Producto sin nombre";
const productPrice = product.price ?? 0;
const imageUrl = product.imageUrl || "/images/product-placeholder.png";
```

---

## 13. Sin valores mágicos

```ts
// Mal
if (products.length > 10) { ... }

// Bien
const MIN_PRODUCTS_FOR_PAGINATION = 10;
if (products.length > MIN_PRODUCTS_FOR_PAGINATION) { ... }
```

---

## 14. TypeScript obligatorio

```ts
type Product = {
  id: number;
  name: string;
  price: number;
  stock: number;
  imageUrl?: string;
};
```

- Props tipadas con `interface Props`
- `as const` para configuración inmutable
- Types en `src/types/{domain}.ts`

---

## 15. Formateadores reutilizables

Centralizar en `src/lib/formatters.ts`:

```ts
export function formatCurrency(value: number) {
  return new Intl.NumberFormat("es-NI", {
    style: "currency",
    currency: "NIO",
  }).format(value);
}

export function formatDate(date: string) {
  return new Intl.DateTimeFormat("es-NI").format(new Date(date));
}
```

---

## 16. Estructura del proyecto

```txt
src/
├── components/
│   ├── ui/                  # 13 UI primitives (Button, Input, ...)
│   ├── storefront/          # auth · b2b · branding · cart · checkout
│   │                        # footer · header · home · layout
│   │                        # products · search
│   ├── account/             # addresses · dashboard · layout · orders · wishlist
│   ├── admin/               # auth · catalog · customers · dashboard · header
│   │                        # inventory · marketing · reports · returns · sales
│   │                        # settings · shipments · sidebar · stores · support
│   └── auth/                # compartido (formularios login/signup)
├── layouts/                 # Layout.astro, StorefrontLayout.astro, AdminLayout.astro
├── pages/
│   ├── index.astro          # redirect a [locale]
│   └── [locale]/            # rutas con locale (es / en)
│       ├── index.astro      # home por tenant
│       ├── products/[slug].astro
│       ├── cart.astro
│       ├── checkout.astro
│       ├── account/         # dashboard, orders, wishlist, addresses, 2fa
│       ├── admin/           # dashboard, catalog, sales, inventory, ...
│       ├── rfq/             # Panda B2B (RFQ + confirmation)
│       ├── bulk-order/      # Panda B2B (precios por volumen)
│       └── search/
├── lib/                     # api.ts, endpoints.ts, formatters.ts, auth.ts
├── config/                  # menu.ts, site.ts
├── i18n/                    # es.json / en.json
├── stores/                  # Estado React (cart, ui)
├── types/                   # TypeScript types por dominio
├── styles/                  # global.css (solo import de Tailwind)
└── assets/                  # Imágenes, iconos estáticos
```

### Storybook (`.storybook/`)

```txt
.storybook/
├── main.ts                  # registra *.stories.@(ts|tsx|js|jsx|astro|mdx)
└── preview.ts               # Preview global + tema por defecto
```

Cada componente de `ui/`, `storefront/`, `account/` lleva su
`*.stories.ts` junto al `.astro` / `.tsx`. Ver
[`docs/frontend/storybook.md`](../docs/frontend/storybook.md
"docs/frontend/storybook.md") para el flujo de trabajo.

---

## 17. HTML semántico y accesibilidad

```html
<!-- Usar tags semánticos -->
<header>  <nav>  <main>  <section>  <footer>  <article>

<!-- Botones reales para acciones -->
<button>Eliminar</button>   <!-- NO: <div onclick="..."> -->

<!-- Imágenes con alt -->
<img src="/product.jpg" alt="Camisa negra estampada" />

<!-- Inputs con label -->
<label for="email">Correo</label>
<input id="email" type="email" />
```

---

## 18. Performance

- Optimizar imágenes
- Lazy loading en imágenes y componentes pesados
- Componentes estáticos (.astro) por defecto; React solo cuando hay interactividad
- Client directives: client:visible > client:load cuando sea posible
- No cargar Chart.js u otras librerías pesadas globalmente
- Paginar tablas grandes
- Evitar renderizar listas enormes completas

---

## 19. Comentarios

Comentar el **por qué**, no el **qué**:

```ts
// Mal
// sumar 1
count = count + 1;

// Bien
// Se usa el total calculado por backend para evitar manipulación desde el cliente
const total = order.serverCalculatedTotal;
```

---

## 20. Principios

| Principio       | Significado                                                |
| --------------- | ---------------------------------------------------------- |
| **KISS**        | No compliques si puede ser simple                          |
| **YAGNI**       | No crees funcionalidades "por si acaso"                    |
| **DRY**         | No repitas lógica, pero no abstraigas demasiado pronto     |
| **SRP**         | Cada archivo = una responsabilidad                         |
| **SoC**         | Separar UI, lógica, datos, validaciones, servicios, types  |
| **Composición** | Componer piezas pequeñas > componente gigante configurable |

---

## 21. Checklist pre-commit

- [ ] El nombre del archivo explica qué hace
- [ ] Una sola responsabilidad
- [ ] Se entiende en < 1 minuto
- [ ] Máximo 2-3 niveles de anidación
- [ ] Sin lógica repetida (3+ veces → abstraer)
- [ ] Nombres claros en funciones y variables
- [ ] Loading, error y empty state manejados
- [ ] Botones son `<button>`, no `<div>`
- [ ] Inputs tienen `<label>`
- [ ] Sin valores mágicos
- [ ] Archivo < 400 líneas
- [ ] Sin lógica de negocio que deba estar en Laravel
- [ ] TypeScript en props y tipos
- [ ] Componente UI nuevo trae su `*.stories.ts` con default + edge cases

## 22. Storybook

Todo componente visual reutilizable debe traer al menos una
story. Convenciones:

- Archivo `Component.stories.ts` junto a `Component.astro` /
  `Component.tsx`.
- Imports desde `@storybook/astro` (no desde `@storybook/react`
  salvo para componentes 100% React).
- Variante `Default` obligatoria + `Loading` / `Empty` / `Error`
  cuando apliquen.
- Datos mockeados localmente en la story; nunca fetch en red.
- Para componentes con tenants (colores Panda/Kitsune), exponer
  una variant por tenant cuando la lógica lo justifique.

Ejecutar Storybook:

```bash
cd frontend
bun run storybook              # http://localhost:6006
bun run storybook:build        # build estático
bun run storybook:test         # smoke test
```

Más detalle en
[`docs/frontend/storybook.md`](../docs/frontend/storybook.md
"docs/frontend/storybook.md").

---

## 23. Islands React dentro de `.astro`

Cuando un componente React se monta como island dentro de un `.astro`,
los atributos del DOM usan la forma **JSX**, no la forma HTML.

### Mapeo de atributos HTML → JSX

| Atributo HTML | Atributo JSX (en TSX) |
|---|---|
| `class` | `className` |
| `for` | `htmlFor` |
| `tabindex` | `tabIndex` |
| `readonly` | `readOnly` |
| `maxlength` | `maxLength` |
| `cellpadding` | `cellPadding` |

### Regla concreta

- En TSX (`.tsx`), usar **siempre** `className`, NUNCA `class`. El
  bundle no aplica la clase al elemento y el estilo se pierde.
- El aviso de React por esto sale sólo por la consola del navegador en
  modo dev. En el build de producción no aparece nada: el error no
  llega a fallar la compilación ni a romper un test.
- `eslint-plugin-react` está instalado, pero la regla
  `react/no-unknown-property` **no está activada** en
  `frontend/eslint.config.js`. Por eso `bun run lint` no te va a marcar
  el `class`. El chequeo de esta tabla es manual y obligatorio.
- La misma idea aplica a cualquier otro atributo renombrado de HTML
  a JSX (`for` → `htmlFor`, `tabindex` → `tabIndex`, etc.). La lista
  completa vive en la doc oficial de React ("DOM Elements").

### Pre-commit de una island

Antes de commitear una island nueva o modificada:

```bash
bun run typecheck          # astro check (TS strict)
bun run lint               # eslint .
```

`bun run lint` **no** cubre el caso `class` → `className` en este
proyecto, porque la regla que lo detectaría no está activada. Revisá la
tabla de atributos de arriba a mano antes de commitear.
