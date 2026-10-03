# Feature: Auth brand consistency (Panda Estampados)

## Objetivo

Alinear el branding visual de las pantallas de autenticación (login, signup)
de Panda Estampados con la identidad del tenant: el color teal `#2D9D8E`
que aparece en las ilustraciones SVG (Storyset/amico), el logo en el header
que no carga por FTP inaccesible en dev, y el orden de columnas del signup
que estaba invertido respecto a login.

## Por qué

Tres bugs visibles detectados por el usuario al revisar
`http://admin.panda-estampados.com/es/account/login` y `.../signup`:

1. **Color verde claro vibrante en botones y links** — el accent se renderiza
   como `#7CB342` (verde bambú del fallback `pandaTheme.brand`) en lugar
   del `#2D9D8E` (teal oscuro del SVG) o el valor dinámico del backend.
   Causa raíz: el middleware de Astro (`src/middleware.ts:117`) hace fetch
   a `${INTERNAL_API_URL}/storefront/theme` pero la ruta real del backend
   es `/api/v1/storefront/theme`. El 404 silencioso hace que el frontend
   use `DEFAULT_THEME` (todo null) y caiga al fallback del brand estático.

2. **Logo de "Panda Estampados" no carga en el header** — solo se ven dos
   letras cortadas. El `AssetController` sirve desde el disk `ftp`
   (`Storage::disk('ftp')`) pero no hay credenciales FTP configuradas en
   `.env` y los archivos tienen `drwx------` (700). El logo sí existe
   físicamente en `/home/vsftpd/dev-ftp/tenants/panda-estampados/branding/logo.webp`.
   Las ilustraciones del fondo (login.svg, signup.svg) sí cargan porque
   son assets estáticos del frontend.

3. **Signup con orden invertido** — `signup.astro` tiene `side="right"`
   (ilustración DER, form IZQ) mientras `login.astro` tiene `side="left"`
   (ilustración IZQ, form DER). Usuario pidió simetría con login.

## Scope — Esta sesión

4 work-unit commits, cada uno aislado (uno extra para destapar un
bug pre-existente del CI de backend que se hizo evidente al pushear):

### Slice 1 — Color teal coherente

- **Frontend `src/middleware.ts`**: arreglar la ruta del fetch del theme
  de `/storefront/theme` → `/api/v1/storefront/theme`.
- **`packages/themes/panda-estampados/src/index.ts`**: sincronizar
  fallback con el SVG → `accentColor: '#2D9D8E'`, `accentHoverColor: '#258777'`.
- **BD dev**: actualizar `store_themes` para `panda-estampados` con
  los hex correctos (vía SQL directo + seed JSON dev).
- **Seeds**: `docker/instances/development/panda-estampados-and-kitsune/seeds/dev-panda-estampados.json`
  y `docker/instances/production/panda-estampados-and-kitsune/seeds/panda.json`.

### Slice 2 — Logo como asset estático

- Copiar `brand-assets/store/panda-estampados/{logo,favicon}.webp` →
  `frontend/public/images/tenants/panda-estampados/branding/`.
- Cambiar `pandaTheme.brand.logoPath` y `loginLogoPath` a la nueva
  ruta estática del frontend.
- Actualizar seeds JSON (dev + prod) + fila `store_themes` en BD dev
  para que el path override-apunte al asset estático (no al FTP).

### Slice 3 — Signup simétrico con login

- `frontend/src/pages/[locale]/account/signup.astro`: `side="right"` →
  `side="left"`.

### Slice 4 (extra, scope `ci`) — Arreglar workflow backend.yml

- Bug pre-existente: `vendor/bin/pint --dirty --format=github` falla
  porque Pint no soporta el formato `github` (válidos: null, checkstyle,
  junit, compact).
- Cambiar a `--test` para validar sin tocar archivos.
- Destapado al pushear este branch: el CI de backend caía en el primer
  job con `Format [github] is not supported`.

## Constraints

- Conventional Commits en español, scope `auth`/`frontend`/`backend`/`seed`
  según el archivo tocado.
- NO `Co-Authored-By`.
- Tamaños: mantener archivos actuales bajo sus límites (AGENTS §1).
- Tailwind orden por intención (AGENTS §9) — no aplica a los archivos
  a tocar (no son componentes visuales nuevos).
- Accesibilidad: el cambio de `side` mantiene orden lógico del DOM
  (mobile sigue mostrando form primero vía CSS `order-1/order-2`).
- Storybook: no aplica (no creo componentes nuevos).
- **NO** modificar backend funcional más allá del seed/JSON (la lógica
  del `AssetController` queda intacta — su fix es ortogonal a esta tarea).

## Acceptance criteria

- 4 commits work-unit (uno por slice), con tests/docs incluidos donde
  aplique.
- Visualmente: en `admin.panda-estampados.com/es/account/login` y
  `.../signup` el botón principal es teal `#2D9D8E`, el logo de Panda
  Estampados se ve en el header, y ambas pantallas tienen la ilustración
  a la izquierda + formulario a la derecha.
- API verificada: `GET /api/v1/storefront/theme` (Host: admin.panda-estampados.com)
  devuelve `accent_color: '#2D9D8E'`.
- Backend: `vendor/bin/pint --dirty` limpio, `php artisan test
  --filter="StorefrontTheme|AdminThemeApi"` pasa.
- Frontend: `pnpm run typecheck && pnpm run lint && pnpm run format:check`
  pasa.
- CI: el job "Lint + Static Analysis" del workflow backend pasa tras
  el fix del flag `--format=github`.

## Routing — Esta sesión

Implementación inline (cambios mecánicos, archivos < 5 cada uno, no hay
resolución de diseño abierta). No requiere writers paralelos.

## Decisiones tomadas

- **Color exacto**: `#2D9D8E` (tomado literalmente del atributo `fill` del
  SVG, verificado con `grep`). Hover: `#258777` (mismo que ya usan los
  fallbacks de `var(--brand-accent-hover)` en `AuthShell.astro`,
  `StorefrontLoginForm.tsx` y `RegisterForm.tsx`).
- **Logo como asset estático en `frontend/public/`** y NO en el FTP.
  Trade-off: rompe el patrón "single-source-of-truth via API" para el
  logo, pero evita la dependencia de FTP en dev. En producción el admin
  puede subir un logo distinto via `AdminThemeController` que override-a
  el del DB (que override-a el del brand estático).
- **No cambio el `AssetController`**: el bug del FTP es ortogonal y
  afecta a productos, favicons y otros assets — su fix va en otra tarea.
- **Signup lado IZQ**: confirmado por el usuario en el plan dialogado.

## Verificación final

- Capturar screenshots antes/después con `playwright-cli` para login y
  signup en el stack dev.
- Diff visual: comparar hex del pixel central del botón submit entre
  screenshots (debe ser `#2D9D8E` ± tolerancia de compresión).

## Relevant Files

- `frontend/src/middleware.ts:117` — fix ruta fetch theme
- `frontend/packages/themes/panda-estampados/src/index.ts` — accent fallback
- `frontend/src/pages/[locale]/account/signup.astro:25` — `side="left"`
- `frontend/public/images/tenants/panda-estampados/branding/logo.webp`
  (nuevo) — logo estático
- `docker/instances/development/panda-estampados-and-kitsune/seeds/dev-panda-estampados.json`
  — accent seed dev
- `docker/instances/production/panda-estampados-and-kitsune/seeds/panda.json`
  — accent seed prod

## Next steps

- PR review + merge a master.
- (Fuera de scope) Tarea separada: arreglar el `AssetController` para
  que el FTP funcione con credenciales correctas, o servir desde
  filesystem local como fallback.
