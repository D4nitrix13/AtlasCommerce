# Frontend — Comandos del día a día

Comandos frecuentes para el frontend Astro.

## Servidor de desarrollo

```bash
cd frontend
pnpm run dev
```

Por defecto en `http://127.0.0.1:4321`.

Build de producción:

```bash
pnpm run build
```

Genera `dist/` con el bundle standalone.

Preview del build:

```bash
pnpm run preview
```

## Verificación de tipos

```bash
pnpm run typecheck
```

Corre `astro check` que valida:

- TypeScript en archivos `.ts` y `.tsx`.
- Tipos en frontmatter de `.astro`.
- Directivas de template.

## Storybook

```bash
# Dev server
pnpm run storybook            # http://localhost:6006

# Build estático (CI, Chromatic, Vercel)
pnpm run storybook:build      # → storybook-static/

# Smoke test del build
pnpm run storybook:test
```

Las stories (`*.stories.ts`) viven junto a cada componente.
Cada componente UI nuevo debe traer su story con variants
`Default`, `Loading`, `Empty`, `Error` cuando aplique.
Ver
[`frontend/storybook.md`](../frontend/storybook.md
"docs/frontend/storybook.md").

## Linter y formateo

```bash
# ESLint
pnpm run lint
pnpm run lint:fix

# Prettier
pnpm run format         # formatea
pnpm run format:check   # solo verifica (modo CI)
```

### Configuración

- ESLint: `eslint.config.js` (flat config).
- Prettier: `.prettierrc.json`.

Excluye:

- `dist/`
- `.astro/`
- `node_modules/`
- `public/`

## Añadir dependencias

```bash
# Producción
pnpm add <paquete>

# Desarrollo
pnpm add -D <paquete>

# Lockfile actualiza
pnpm install
```

Importante: commiteá `pnpm-lock.yaml` con cada cambio de
dependencias.

## Estructura de imports

```ts
// Imports externos
import { useState } from "react";
import type { User } from "react-hook-form";

// Imports internos absolutos
import { apiFetch } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { LoginForm } from "@/components/admin/auth/LoginForm";
```

Path alias `@/` apunta a `src/` (configurado en `tsconfig.json`).

## i18n

```bash
# Agregar una key
# 1. Editar src/i18n/locales/es.ts
# 2. Editar src/i18n/locales/en.ts
# 3. Verificar tipos con pnpm run typecheck
```

## Componentes

### Crear componente UI

```bash
# Crear archivo
touch src/components/ui/Button.astro

# Estructura mínima
```

```astro
---
interface Props {
  label: string;
  onClick?: () => void;
}

const { label, onClick } = Astro.props;
---

<button onclick={onClick}>{label}</button>
```

### Crear componente React

```bash
touch src/components/admin/users/UserCard.tsx
```

```tsx
import { useState } from "react";

interface Props {
  userId: number;
}

export default function UserCard({ userId }: Props) {
  const [user, setUser] = useState(null);
  return <div>User {userId}</div>;
}
```

## Debugging

### Astro Dev Toolbar

Aparece en la esquina inferior izquierda del browser. Permite:

- Inspeccionar props.
- Ver logs de auditoría.
- Probar locale switching.

### Logs del dev server

```bash
# Si arrancaste con nohup
tail -f /tmp/frontend.log

# Si arrancaste con pnpm run dev
# Los logs aparecen en la terminal
```

### DevTools del browser

- React DevTools (para componentes `React`).
- Network tab para inspeccionar `fetch` API.
- Astro DevTools (extensión).

## Build optimizations

```bash
# Build normal
pnpm run build

# Build con análisis de bundle
pnpm run build -- --verbose

# Ver warnings
pnpm run astro -- check
```

## Troubleshooting

### `Cannot find module '...'`

```bash
rm -rf node_modules .astro
pnpm install
```

### Cambios en `astro.config.mjs` no aplican

Reiniciar el dev server (`Ctrl+C` + `pnpm run dev`).

### `Blocked request. This host is not allowed`

Host no está en `vite.server.allowedHosts` en `astro.config.mjs`.

### `Module not found: Can't resolve 'X'`

Verificar instalación:

```bash
pnpm install
```

Verificar alias en `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["src/*"]
    }
  }
}
```

## Próximo archivo

[testing.md](testing.md "testing.md") — Tests con Playwright.
