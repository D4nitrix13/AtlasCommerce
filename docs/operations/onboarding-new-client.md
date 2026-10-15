# AtlasCommerce — Onboarding de un nuevo cliente (tenant)

> Procedimiento paso a paso para agregar un nuevo cliente (tenant) a
> AtlasCommerce SaaS. La Voz de Nicaragua fue el primer cliente real.
> Este doc sirve como template para los próximos.

## Pre-requisitos

- Código del repo actualizado a master
- Docker compose v2
- Bun (o pnpm >= 9) instalado
- Acceso al host donde corre el dev stack
- Brand del cliente conocido:
  - Slug del tenant (kebab-case, sin acentos, ej `instrumentos-musicales-la-voz`)
  - Dominios reales (prod y dev)
  - Dominio admin
  - Colores corporativos (accent + hover + neutros)
  - Logo (SVG o PNG)
  - Email de soporte, teléfono, WhatsApp
  - Categorías de catálogo (3-6 categorías iniciales)

## Pasos

### 1. Crear el paquete de tema

```bash
mkdir -p frontend/packages/themes/<slug>
```

Crear `package.json`:
```json
{
  "name": "@theme/<slug>",
  "version": "0.1.0",
  "type": "module",
  "files": ["src"],
  "exports": { ".": "./src/index.ts" }
}
```

Crear `src/index.ts` con la estructura:
```ts
export const <slugPascalCase>Theme = {
  brand: {
    slug: '<slug>',
    name: '<Nombre del Cliente>',
    shortName: '<Corto>',
    domains: ['<dominio-prod>', 'www.<dominio-prod>'],
    adminDomains: ['admin.<dominio-prod>', 'admin.localhost', 'admin.127.0.0.1'],
    logoPath: '/images/tenants/<slug>/branding/logo.svg',
    loginLogoPath: '/images/tenants/<slug>/branding/logo.svg',
    accentColor: '#XXXXXX',
    accentHoverColor: '#XXXXXX',
    supportEmail: 'soporte@<dominio>',
    supportPhone: '+<código país> <número>',
    supportWhatsapp: '<número solo dígitos>',
    loginTheme: { bg, surface, surfaceHover, border, borderStrong, text, textMuted, textSubtle, inputBg, inputBgFocus, danger, dangerText, dangerBg, divider },
  },
  tokens: { fontFamily: 'Inter', radius: '0.5rem' },
} as const;
export default <slugPascalCase>Theme;
```

### 2. Registrar el tenant en el frontend

Editar `frontend/src/config/stores.ts`:
- Agregar import del theme
- Agregar entrada en `STORE_BRANDS` map

```ts
import { <slugPascalCase>Theme } from '@theme/<slug>';

export const STORE_BRANDS: Record<string, StoreBrand> = {
  ...,
  '<slug>': <slugPascalCase>Theme.brand,
};
```

### 3. Instalar el symlink del theme

```bash
cd frontend && pnpm install
```

Si el symlink no se crea (problema de cache conocido), correr:
```bash
cd /app && yes | pnpm install
```

### 4. Crear instancia Docker dev

Copiar la estructura de un store existente:
```bash
cp -r docker/instances/development/morales-store docker/instances/development/<slug>
```

Reemplazar en todos los archivos:
- `morales-store` → `<slug>` (kebab case)
- `Morales` → `<Nombre del cliente>` o `<SLUG>` (Pascal case)
- Container names: `<slug>-dev-...` con slugs unicos
- Volume names: `atlas_commerce_<slug>_dev_*`
- Network: `atlas-commerce-<slug>-dev`

**Puertos**: usar shift +2000 sobre defaults para no chocar con otros stores. Editar `environment/.env`:
- POSTGRES_PORT=11432 (or similar)
- REDIS_PORT=11379
- BACKEND_PORT=14000
- FRONTEND_PORT=10321
- Etc.

**Hosts editando `docker-stack.sh`** o `scripts/docker/_lib/resolve.sh`:
```bash
STORES=(panda-estampados-and-kitsune mm-boutique morales-store <slug>)
```

### 5. Hardcodear puertos en docker-compose

Bug conocido de docker compose v2 con `${VAR:-default}` en ports:
no resuelve la variable en `ports:`. **Siempre hardcodear** los
puertos en `docker-compose.yml`:
```yaml
ports:
  - "10321:4321"   # no usar "${FRONTEND_PORT:-10321}:4321"
```

Esto es valido para todas las instancias. Mantener `.env` con las
variables para uso en `environment:` y referencias internas.

### 6. Update el script de orquestador

`scripts/docker-stack.sh` y `scripts/docker/_lib/resolve.sh`:
- Agregar `<slug>` a la lista `STORES`
- Actualizar el help text con el nuevo store

### 7. Update el entrypoint del frontend

`docker/instances/development/<slug>/frontend/entrypoint.development.sh`:
- El pnpm install en el entrypoint usa `--prefer-offline --force`
  (sin confirmacion interactiva) para que el named volume se sincronice
  con workspaces nuevos sin imagen rebuild.

### 8. Mock data del catalogo

Crear `frontend/src/data/<slug>.ts` con:
- 5-6 categorías de productos
- 10-15 productos mock con precios en moneda local
- Subcategorías si aplica

Cargar los productos via `products.ts` o donde el index page los consuma.

### 9. Validar build

```bash
cd frontend && bun run astro check
cd docker/instances/development/<slug> && docker compose build frontend
docker compose -f docker/instances/development/<slug>/docker-compose.yml down
docker compose -f docker/instances/development/<slug>/docker-compose.yml up -d
curl -ks --max-time 5 -H "Host: <dominio-dev>" http://127.0.0.1:<FRONTEND_PORT>/es
```

### 10. Documentar y commitear

- Crear branch `feat/tenant-<slug>`
- Conventional commits en español
- PR al master con checklist de onboarding completado
- Update `odd/tasks/clients.md` con la lista de clientes activos

## Lecciones aprendidas

- **Volúmenes nombrados shadowan el bind-mount del host** (los workspaces nuevos no se ven sin `pnpm install` en el entrypoint)
- **Docker compose v2 no resuelve variables en `ports:`** (siempre hardcodear puertos en docker-compose.yml)
- **Symlinks de workspace se rompen con pnpm install en cache** (forzar con `pnpm install --force` en entrypoint)
- **El symlink inicial no se crea sin `pnpm install` en el volumen** (la primera vez hay que correrlo manualmente)

## Clientes activos

- `panda-estampados` (legacy dev-only, no cliente real)
- `kitsune` (legacy dev-only)
- `mm-boutique` (legacy dev-only)
- `morales-store` (legacy dev-only)
- `instrumentos-musicales-la-voz` (PRIMER CLIENTE REAL — Tropical Music Nicaragua)
