# Frontend — Astro 7 + React 19

## Visión general

Un solo build de `Astro` sirve la tienda pública y el panel
administrativo. El mismo código se despliega bajo diferentes dominios
y el middleware detecta el tenant según el `Host` header.

## Versiones

| Componente          | Versión  |
| ------------------- | -------- |
| `Astro`             | 7.x      |
| `React`             | 19.x     |
| `Tailwind CSS`      | 4.x      |
| `TypeScript`        | 6.x      |
| `Zod`               | 4.x      |
| `react-hook-form`   | 7.x      |
| `Chart.js`          | 4.x      |
| `lucide-react`      | 1.x      |
| `Node.js`           | 22.12+   |
| `pnpm`              | 9.x      |

## Instalación

### 1. Instalar `Node.js` 22.12+

```bash
# Recomendado: usar nvm o fnm para múltiples versiones
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
nvm install 22.12
nvm use 22.12

# macOS via Homebrew
brew install node@22
```

### 2. Instalar `pnpm` 9+

```bash
npm install -g pnpm@9
```

### 3. Instalar dependencias del proyecto

```bash
cd frontend
pnpm install --frozen-lockfile
```

`package.json` declara las dependencias:

```json
{
  "dependencies": {
    "@astrojs/check": "^0.9.9",
    "@astrojs/node": "^11.0.3",
    "@astrojs/react": "^6.0.0",
    "@tailwindcss/vite": "^4.3.1",
    "astro": "^7.0.1",
    "chart.js": "^4.5.1",
    "react": "^19.2.7",
    "react-hook-form": "^7.80.0",
    "tailwindcss": "^4.3.1",
    "zod": "^4.4.3"
  },
  "devDependencies": {
    "@eslint/js": "^9.10.0",
    "eslint": "^9.10.0",
    "eslint-config-prettier": "^9.1.0",
    "eslint-plugin-astro": "^1.3.0",
    "eslint-plugin-react": "^7.36.0",
    "eslint-plugin-react-hooks": "^5.0.0",
    "prettier": "^3.3.3",
    "typescript": "^6.0.3",
    "typescript-eslint": "^8.5.0"
  }
}
```

## Estructura del proyecto

```txt
frontend/
├── public/                  # Assets estáticos (imágenes, favicons)
├── src/
│   ├── assets/              # Imágenes internas
│   ├── components/          # Componentes Astro + React
│   │   ├── ui/              # Componentes UI genéricos
│   │   ├── public/          # Storefront components
│   │   ├── admin/           # Panel admin components
│   │   └── ecommerce/       # Cart, checkout
│   ├── config/              # menu.ts, site.ts, stores.ts
│   ├── data/                # Mock data (solo dev)
│   ├── i18n/                # Sistema de internacionalización
│   ├── layouts/             # Layout.astro, AdminLayout.astro
│   ├── lib/                 # api.ts, endpoints.ts, formatters.ts
│   ├── pages/               # File-based routing
│   │   ├── [locale]/        # Rutas localizadas
│   │   │   └── admin/       # Panel admin
│   │   ├── auth/            # Storefront login/register
│   │   └── ...              # Páginas públicas
│   ├── stores/              # React state (auth, cart, ui)
│   ├── styles/              # global.css con Tailwind
│   └── types/               # TypeScript types por dominio
├── astro.config.mjs         # Config de Astro
├── eslint.config.js         # ESLint 9 flat config
├── package.json
├── pnpm-lock.yaml
└── tsconfig.json
```

## Comandos principales

### Levantar el servidor de desarrollo

```bash
cd frontend
pnpm run dev
```

Por defecto escucha en `http://127.0.0.1:4321`.

Build de producción:

```bash
pnpm run build      # compila a ./dist/
pnpm run preview    # sirve el build
```

### Verificación de tipos

```bash
pnpm run typecheck   # astro check (TypeScript + Astro)
```

### Linter y formateo

```bash
# ESLint
pnpm run lint        # revisar
pnpm run lint:fix    # arreglar

# Prettier
pnpm run format         # formatea
pnpm run format:check   # solo verifica
```

## Variables de entorno

Archivo `frontend/.env`:

```env
PUBLIC_API_URL=http://localhost:8080/api
PUBLIC_APP_NAME="Kitsune Store"
```

| Variable             | Descripción                                |
| -------------------- | ------------------------------------------ |
| `PUBLIC_API_URL`     | URL base del backend Laravel (con `/api`) |
| `PUBLIC_APP_NAME`    | Nombre del app (legacy, en desuso)       |

## Internacionalización

- `src/i18n/config.ts` define locales soportados (`es`, `en`).
- `src/i18n/locales/*.ts` contiene los mensajes por idioma.
- Astro i18n nativo con `prefixDefaultLocale: true`.
- Middleware en `src/middleware.ts` aplica la detección de tenant.

## Multi-tenant y subdominios

El middleware en `src/middleware.ts` hace dos cosas:

1. **Detecta el tenant** por `Host` header:
   - `panda-estampados.com` → tenant `panda`
   - `admin.panda-estampados.com` → tenant `panda`, contexto admin
   - `kitsune.com` → tenant `kitsune`
2. **Rewrite de URLs admin** limpias:
   - `admin.kitsune.com/login` → `/es/admin/auth/login` (interno)

Branding dinámico desde `src/config/stores.ts` (`STORE_BRANDS`).

## Cliente API

- `src/lib/api.ts` — wrapper `fetch` con autenticación.
- `src/lib/endpoints.ts` — centraliza endpoints por dominio.
- `src/stores/auth.ts` — store de autenticación con React 19.

## Documentación relacionada

- [architecture/overview.md](../architecture/overview.md "architecture/overview.md")
- [architecture/multi-store.md](../architecture/multi-store.md "architecture/multi-store.md")
- [getting-started/frontend-setup.md](../getting-started/frontend-setup.md "getting-started/frontend-setup.md")
- [frontend/AGENTS.md](../../frontend/AGENTS.md "frontend/AGENTS.md")
- [frontend/README.md](../frontend/README.md "frontend/README.md")
