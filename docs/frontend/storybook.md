# Storybook 10 — Catálogo de Componentes

> **Objetivo**: dar a devs y diseñadores un entorno visual
> para desarrollar, documentar y revisar UI primitives +
> componentes de storefront/account sin necesidad de levantar
> el backend ni el navegador.
>
> **Stack**: Storybook `10.6.0` + `@storybook-astro/framework`
> `1.12.0` + addons `@storybook/addon-docs` y
> `@storybook/addon-a11y`.

## 1. Setup

`Storybook` ya está instalado en `frontend/` y configurado en
[`frontend/.storybook/`](../../frontend/.storybook/
"frontend/.storybook/"). No requiere pasos adicionales.

```json
// frontend/package.json (fragmento)
{
  "scripts": {
    "storybook": "storybook dev -p 6006",
    "storybook:build": "storybook build",
    "storybook:test": "bun run storybook:build --quiet"
  },
  "devDependencies": {
    "storybook": "^10.6.0",
    "@storybook-astro/framework": "^1.12.0",
    "@storybook/addon-docs": "^10.6.0",
    "@storybook/addon-a11y": "^10.6.0",
    "@storybook/react-vite": "^10.6.0"
  }
}
```

Storybook internamente requiere dependencias hoisteadas.
Bun aplica el patrón `hoist` por defecto (similar al nodo flat de
pnpm), por lo que no hace falta `.npmrc` ni configuración
adicional. Ver [Storybook FAQ]
[storybook-faq].

[storybook-faq]: https://storybook.js.org/docs/react/faq#using-strict-pnpm-workspaces "Storybook FAQ"

## 2. Configuración

### `frontend/.storybook/main.ts`

```ts
import type { StorybookConfig } from '@storybook-astro/framework';

const config: StorybookConfig = {
  stories: ['../src/**/*.stories.@(ts|tsx|js|jsx|astro|mdx)'],
  addons: [
    '@storybook/addon-docs',
    '@storybook/addon-a11y',
  ],
  framework: {
    name: '@storybook-astro/framework',
    options: {},
  },
  docs: {},
};

export default config;
```

Cualquier componente que cumpla el glob
`*.stories.@(ts|tsx|js|jsx|astro|mdx)` se levanta
automáticamente — no hay que registrarlo manualmente.

### `frontend/.storybook/preview.ts`

Define la Preview global (parámetros, decorators, theme).

## 3. Cómo correrlo

```bash
cd frontend
bun install                   # primera vez (o cuando cambian deps)
bun run storybook             # http://localhost:6006
```

Para CI:

```bash
bun run storybook:build       # genera storybook-static/
bun run storybook:test        # smoke test (build silencioso)
```

El build estático se sirve en
`frontend/storybook-static/` y puede deployarse a Chromatic,
Vercel, Netlify o un Nginx interno para QA visual.

## 4. Cómo crear una story

### Convención de ubicación

La story vive **junto al componente** con el sufijo
`*.stories.ts` (o `.astro` para `.astro` puro). Storybook la
detecta automáticamente.

```txt
frontend/src/components/ui/Button.tsx
frontend/src/components/ui/Button.stories.ts
frontend/src/components/storefront/cart/CartItem.astro
frontend/src/components/storefront/cart/CartItem.stories.ts
```

### Plantilla mínima (componente React / Astro con props)

```ts
import type { Meta, StoryObj } from '@storybook/astro';
import Button from './Button.astro';

const meta: Meta<typeof Button> = {
  title: 'UI/Button',
  component: Button,
  args: {
    label: 'Guardar',
    variant: 'primary',
  },
  argTypes: {
    variant: {
      control: { type: 'select' },
      options: ['primary', 'secondary', 'ghost'],
    },
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Disabled: Story = {
  args: { disabled: true },
};

export const Loading: Story = {
  args: { label: 'Guardando…', isLoading: true },
};
```

### Plantilla para componente React puro

```ts
import type { Meta, StoryObj } from '@storybook/react';
import { LanguageSwitcher } from './LanguageSwitcher';

const meta: Meta<typeof LanguageSwitcher> = {
  title: 'UI/LanguageSwitcher',
  component: LanguageSwitcher,
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: { locale: 'es', onChange: () => {} },
};
```

### Plantilla con mock data y controles

```ts
import type { Meta, StoryObj } from '@storybook/astro';
import ProductCard from './ProductCard.astro';

const meta: Meta<typeof ProductCard> = {
  title: 'Storefront/ProductCard',
  component: ProductCard,
  args: {
    product: {
      id: 1,
      name: 'Saber Alter — Fate/Stay Kaynan',
      price: 14500,
      currency: 'USD',
      imageUrl: '/images/product-placeholder.png',
      stock: 3,
    },
  },
};

export default meta;

export const Default: Story = {};

export const Featured: Story = {
  args: { product: { /* … */ }, featured: true },
};

export const OutOfStock: Story = {
  args: { product: { /* … */, stock: 0 } },
};
```

## 5. Convenciones del proyecto

- Imports desde `@storybook/astro` por defecto; solo
  `@storybook/react` cuando el componente es 100% React.
- **`title` en PascalCase con namespace** —
  `UI/Button`, `Storefront/CartItem`,
  `Account/Dashboard/QuickActionCard`.
- **Variantes obligatorias** cuando apliquen:
  `Default`, `Loading`, `Empty`, `Error`, `Disabled`.
- **Datos mockeados** locales; nunca fetch en red.
- **Multi-tenant** — exponer variant por tenant
  (`Panda`, `Kitsune`) cuando el componente lee colores
  dinámicos.
- Accesibilidad — el addon `@storybook/addon-a11y` corre
  auditoría automática; revisar el panel "Accessibility".

## 6. Cobertura actual (a 2026-09-30)

23 stories registradas:

### UI primitives (13)

```txt
Alert, Badge, Breadcrumb, Button, Card, Dropdown,
Icon, Input, Modal, Pagination, Select, Table, Textarea
```

### Storefront (4)

```txt
Home/HeroBanner
Products/ProductCard
Products/ProductGrid
Cart/CartItem
Cart/CartSummary
```

### Account (5)

```txt
Dashboard/QuickActionCard
Dashboard/QuickActionsGrid
Dashboard/DashboardWelcomeBanner
Dashboard/RecentOrdersList
Wishlist/WishlistItem
```

> Si agregás un componente UI nuevo, agregá también su
> `*.stories.ts` antes de mergear. Es parte del flujo
> definido en
> [`CONTRIBUTING.md`](../../CONTRIBUTING.md "CONTRIBUTING.md").

## 7. Troubleshooting

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `pnpm storybook` falla con "Cannot find module" | Dependencias internas no hoisteadas | Verificar que `frontend/.npmrc` incluya `public-hoist-pattern[]=*storybook*` y correr `pnpm install` |
| Story no aparece en el sidebar | Glob no matchea | Verificar que termina en `*.stories.ts` (no `.tsx`) salvo revisión concreta |
| Astro component no renderiza | Falta `client:` directive en el iframe | Storybook Astro a veces requiere `client:load` explícito para componentes interactivos |
| Addon a11y reporta violaciones | Color contrast / aria attrs | Resolver en el componente, no en la story |

## 8. Recursos

- [Storybook 10 docs](https://storybook.js.org/docs/10.0 "Storybook 10")
- [Storybook Astro framework](https://github.com/storybookjs/Storybook/tree/main/code/frameworks/astro "Storybook Astro")
- [Chromatic](https://www.chromatic.com/ "Chromatic") — visual regression
  testing (no integrado todavía)
