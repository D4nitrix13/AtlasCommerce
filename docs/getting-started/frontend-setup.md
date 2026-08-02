# Frontend — Setup de Astro

Configurar el frontend con sus variables de entorno y verificar
que el dev server arranque.

## 1. Variables de entorno

```bash
cd frontend
cp .env.example .env
```

Editá `frontend/.env`:

```env
PUBLIC_API_URL=http://localhost:8080/api
PUBLIC_APP_NAME="Kitsune Store"
```

### Variables disponibles

| Variable          | Descripción                                |
| ----------------- | ------------------------------------------ |
| `PUBLIC_API_URL`  | URL base del backend (incluye `/api`)     |
| `PUBLIC_APP_NAME` | Nombre del app (legacy, en desuso)       |

En Astro, todo lo que empieza con `PUBLIC_` se expone al cliente. Las
demás son server-side.

## 2. Hosts permitidos para Vite

Ya están configurados en `astro.config.mjs`:

```js
vite: {
  server: {
    allowedHosts: [
      "pandas-estampados.com",
      "www.pandas-estampados.com",
      "admin.pandas-estampados.com",
      "kitsune.com",
      "www.kitsune.com",
      "admin.kitsune.com",
      "localhost",
      "admin.localhost",
    ],
  },
}
```

Si agregás un dominio nuevo, sumalo también a esta lista.

## 3. Verificar el dev server

```bash
cd frontend
pnpm run dev
```

Por defecto arranca en `http://127.0.0.1:4321`.

Verificar:

```bash
curl -sI http://127.0.0.1:4321/
# HTTP/1.1 302 Found
# location: /es
```

El 302 es el redirect del middleware que agrega el locale prefix.

## 4. Verificar chequeo de tipos

```bash
pnpm run typecheck
```

`astro check` corre el verificador de TypeScript sobre `.astro` y
archivos `.ts`/`.tsx`. Si hay errores, los lista antes de levantar.

## 5. Configurar el cliente API

El archivo `src/lib/api.ts` lee `PUBLIC_API_URL` desde el entorno:

```ts
const API_URL = (import.meta.env.PUBLIC_API_URL ?? "").replace(/\/$/, "");
```

Si la variable no está definida, lanza un error claro al primer `fetch`.

Endpoints centralizados en `src/lib/endpoints.ts`:

```ts
export const API_ENDPOINTS = {
  auth: {
    login: "/auth/login",
    register: "/auth/register",
    me: "/auth/me",
  },
  catalog: {
    products: "/products",
  },
  // ...
};
```

## 6. Sistema de i18n

Locales soportados: `es`, `en`. Config en `src/i18n/config.ts`.

Mensajes en `src/i18n/locales/{es,en}.ts`.

Para agregar un nuevo locale:

1. Agregar el código a `SUPPORTED_LOCALES` en `src/i18n/config.ts`.
2. Crear `src/i18n/locales/<code>.ts` con todas las keys.
3. Agregar el `<code>` a `locales` en `astro.config.mjs`.

## 7. Branding por tienda

`src/config/stores.ts` define `STORE_BRANDS` con:

- `slug` (Panda, Kitsune).
- `name` (Panda Estampados, Kitsune Store).
- `domains` (lista de dominios principales).
- `adminDomains` (subdominios admin).
- `logoPath`, `loginLogoPath`.
- `accentColor`, `accentHoverColor`.
- `supportEmail`.

Para agregar una tienda nueva:

1. Agregar entrada en `STORE_BRANDS`.
2. Seeder de `Store` + `StoreDomain` con sus dominios.
3. Verificar que el middleware detecta el host.

## 8. Build de producción

```bash
pnpm run build
```

Genera `dist/` con el bundle standalone de Astro (adapter Node).

```bash
pnpm run preview
```

Sirve el bundle localmente.

## 9. Problemas comunes

### `Blocked request. This host is not allowed`

Agregar el host a `allowedHosts` en `astro.config.mjs`.

### `PUBLIC_API_URL is not configured`

Falta definir la variable en `.env`. Ver:

```bash
cat frontend/.env
```

### `Module not found` en runtime

Las dependencias no están instaladas:

```bash
rm -rf node_modules
pnpm install
```

### Cambios en `astro.config.mjs` no se aplican

Reiniciar el dev server:

```bash
# Ctrl+C para parar
pnpm run dev
```

## Próximo paso

[local-domains.md](local-domains.md "local-domains.md") — Configurar
dominios locales para multi-tienda.
