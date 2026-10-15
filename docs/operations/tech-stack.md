# Stack tecnológico de AtlasCommerce

> **Última actualización**: 2026-10-15 (Track 1 del sprint
> `chore/saas-production-polish` Q1 2026).
>
> **Propósito**: documento de referencia única para decisiones de stack,
> razones de cada elección y roadmap de migraciones futuras. Cuando se
> evalúa cambiar una pieza, se discute acá primero antes de tocar código.

## Stack actual (producción-ready)

| Capa | Tecnología | Versión pineada | Por qué |
| --- | --- | --- | --- |
| **Backend runtime** | PHP + Laravel + Sanctum | 8.4 · 13.8 · 13 | Laravel 13 es estable y trae el stack completo que necesitamos (Sanctum para auth, Queue con Redis, cache, broadcasting). PHP 8.4 con readonly classes + property hooks habilita el código defensivo de AtlasCommerce. |
| **Frontend framework** | Astro + React + Tailwind + TypeScript | 7 · 19 · 4 · 6 | Astro 7 con SSR + islands Reactivas selectivas. Componentes `.astro` para 90% de las vistas (zero JS) + React solo donde hay interactividad real (form, ATC, etc). Tailwind 4 con CSS custom por componente. |
| **Frontend package manager** | **bun** (no pnpm, no npm) | 1.4 | Ver `§ Migraciones realizadas > pnpm → bun`. |
| **Multi-tenant themes** | Astro workspace `@theme/*` con CSS vars | n/a | Cada tenant (Panda, Kitsune, MM, Morales, La Voz) tiene su paquete `@theme/<slug>` que exporta `--primary` y overrides. El frontend importa el del tenant detectado vía middleware. Cero rebuilds para cambiar de tienda. |
| **Base de datos** | PostgreSQL + PostGIS | 18 · 3.6 | Postgres es estable, PostGIS overkill para el MVP actual (e-commerce sin geografía) — ver tradeoffs en `§ Tradeoffs`. |
| **Cache / Colas** | Redis | 7 | Cache de Laravel, sesiones, queue de jobs (emails, auditoría, reportes), broadcast. |
| **Storage** | Volúmenes Docker con bind-mount en dev | n/a | Cada tienda tiene su volumen en producción; dev usa bind-mount para iterar sin rebuild. |
| **Reverse proxy** | Caddy | 2.8 | Caddy = config mínima, auto-TLS vía Let's Encrypt, log estructurado. Vs nginx: ~30% menos config para el mismo caso de uso. Vs Traefik: menos magia. |
| **Infra** | Docker Compose v2 | 5+ | Multi-stage builds para minimizar imagen runtime, named volumes para estado, networks internas para service discovery. |
| **Quality backend** | PHPUnit + Pint + PHPStan | 12 · latest · latest | Pint = formatter (estilo Laravel). PHPStan = static analysis nivel 9 con Larastan. |
| **Quality frontend** | Vitest (unit) + Playwright (e2e + a11y WCAG 2.2 AA) + Storybook | 4 · latest · 10 | axe-core cubriendo RF-USA-043 (WCAG 2.2 AA). |
| **CI/CD** | GitHub Actions | n/a | Backend CI: backend-ci → lint + phpstan + pint + tests. Frontend CI: frontend-ci → typecheck + lint + vitest + e2e Playwright + Storybook smoke. |
| **Documentación** | Markdown + archify (diagramas HTML standalone) | n/a | `archify` skill local para generar diagramas on-demand. |

## Diagrama de flujo

```text
                ┌─────────────────────────────────────────────────┐
                │                  Browser cliente                │
                └────────────────────────┬────────────────────────┘
                                         │ HTTPS (TLS via Caddy)
                                         ▼
            ┌────────────────────────────────────────────────────────┐
            │           Caddy (reverse proxy + TLS)                 │
            │  Rutea por Host header a la instancia correcta         │
            └────┬──────────────────┬──────────────────┬─────────────┘
                 │                  │                  │
                 ▼                  ▼                  ▼
      ┌──────────────────┐ ┌─────────────────┐ ┌──────────────────┐
      │ panda-estampados │ │   mm-boutique   │ │  morales-store   │
      │  +kitsune (B2B   │ │   (B2C moda)    │ │  (retail)        │
      │   +B2C multi)    │ │                 │ │                  │
      │                  │ │                 │ │                  │
      │ ┌──────────────┐ │ │ ┌─────────────┐ │ │ ┌──────────────┐ │
      │ │ Astro SSR    │ │ │ │ Astro SSR   │ │ │ │ Astro SSR    │ │
      │ │ puerto 4321  │ │ │ │  4321       │ │ │ │  4321        │ │
      │ └──────┬───────┘ │ │ └──────┬──────┘ │ │ └──────┬───────┘ │
      │        │         │ │        │        │ │        │         │
      │        ▼         │ │        ▼        │ │        ▼         │
      │ ┌──────────────┐ │ │ ┌─────────────┐ │ │ ┌──────────────┐ │
      │ │ Laravel API  │ │ │ │ Laravel API │ │ │ │ Laravel API  │ │
      │ │ FPM :8000    │ │ │ │ FPM :8000   │ │ │ │ FPM :8000    │ │
      │ └──────┬───────┘ │ │ └──────┬──────┘ │ │ └──────┬───────┘ │
      └────────┼─────────┘ └────────┼────────┘ └────────┼─────────┘
               │                    │                   │
               └────────────────┬───┴───────────────────┘
                                │
                  ┌─────────────┴─────────────┐
                  ▼                           ▼
         ┌────────────────────┐      ┌────────────────────┐
         │ PostgreSQL + Post  │      │ Redis              │
         │ GIS (por instancia, │      │ (compartido por    │
         │ puerto shifted)    │      │ stage, cache/queue)│
         └────────────────────┘      └────────────────────┘
```

Cada tienda tiene **DB propia + volúmenes propios** (aislamiento total).
Redis es **compartido por stage** (cache + queues compartidos, bajo costo).

## Migraciones realizadas

### pnpm → bun (commit 51deaddf + cierre T1.1 sprint actual)

| Aspecto | pnpm | bun |
|---|---|---|
| Velocidad de instalación | ~12s en lockfile medio | ~1.5s |
| Gestión de workspaces | Adecuada | Nativa (más simple) |
| Lockfile | `pnpm-lock.yaml` | `bun.lock` (JSON text) |
| Runtime | Solo package manager | **También runtime + bundler + test runner** |
| Compatibilidad Storybook 10 + Astro 7 | OK | OK |
| Comando de scripts | `pnpm run X` | `bun run X` |
| Equivalente `--frozen-lockfile` | `pnpm install --frozen-lockfile` | `bun install --frozen-lockfile` |
| Equivalente `pnpm exec` (CLI binaria) | `pnpm exec <bin>` | `bunx <bin>` |
| Hoist workspaces para Storybook | `public-hoist-pattern` en `.npmrc` | `linker = "hoisted"` en `bunfig.toml` + `trustedDependencies` |

**Detalles**: Ver docs en commits `ff247e15` (docker) y `cadf7a23` (frontend docs).

### Multi-tenant antes (config monolítica) → ahora (workspaces @theme/*)

Antes: una sola carpeta `frontend/src/` con archivos `panda.config.ts`,
`kitsune.config.ts`, switches if-else en componentes. Después: cada tenant
tiene su paquete `@theme/<slug>` con sus tokens (color, font, espaciado)
y se importa condicionalmente según `Astro.locals.tenant`.

**Beneficio**: cambios de un tenant no rompen otros. Nuevas tiendas se
agregan creando `@theme/<slug>/` + el switcher en `resolve.ts`.

## Tradeoffs conocidos (y por qué los aceptamos)

| Decisión | Tradeoff | Mitigación |
|---|---|---|
| **Postgres + PostGIS** | PostGIS es overkill para e-commerce sin geografía (búsqueda por distancia, etc). | Si nunca lo usamos, se puede remover en sprint futuro y bajar el tamaño de la imagen Docker. Costo: ~50MB. |
| **Caddy vs Traefik vs nginx** | Caddy es menos conocido en enterprise vs nginx. Traefik mete un sidecar que duplica la abstracción. | Caddy tiene auto-TLS, config declarativa simple, log estructurado. La ganancia de simplicidad outweigh la familiaridad de nginx. Si migramos, ver `§ Roadmap > Traefik`. |
| **Bun en Docker + corepack** | Bun cambia rápido (1.x → 2.x puede traer breaking). | Pineado por `corepack prepare bun@1.4 --activate` en Dockerfile. Si migramos a Bun 2.x, se hace en un solo Dockerfile y un solo `bunfig.toml`. |
| **Storybook 10 + Astro framework** | `@storybook/astro` es relativamente nuevo (1.x). | Funciona bien para el caso Bonfire. Si rompe, tenemos las stories en archivos `.stories.ts` planos que se pueden migrar a Histoire (Astro-native). |
| **Astro 7 SSR standalone vs Vite plugin** | SSR standalone agrega un paso de build (bundle). | Es ~2% más lento en dev, pero el bundle es portable y no requiere Node en runtime de manera especial. |
| **No OpenAPI / Scalar aún** | Documentar API a mano (este archivo) en lugar de generado. | No hay decisión tomada. Ver `§ Roadmap > Scalar / OpenAPI`. |

## Roadmap de migraciones futuras

Evaluadas, no aplicadas todavía (cada una con su propio sprint de tareas
work-unit si se aprueba):

### Pendientes (priorizadas por impacto)

1. **Scalar / OpenAPI** (impacto alto, costo bajo).
   - **Qué**: generar spec OpenAPI desde Laravel (l5-swagger o
     darkaonline/l5-swagger) y servirla en `/api/docs` con
     `@scalar/api-reference` integrado en el frontend Astro.
   - **Beneficio**: developers externos / frontend pueden descubrir la API
     sin leer código fuente. Onboarding de nuevos devs 50% más rápido.
   - **Costo**: 2-3 días (spec + UI + autenticar Scalar con Sanctum).
   - **Estado**: pendiente decisión.

2. **FrankenPHP en lugar de PHP-FPM + nginx sidecar** (impacto medio,
   costo medio).
   - **Qué**: servir Laravel desde un solo binario PHP con HTTP server
     nativo (Caddy integrado). Elimina el sidecar `nginx` en cada
     instancia y simplifica el `docker-compose.yml`.
   - **Beneficio**: 1 layer menos, imagen backend más liviana, ~2x throughput.
   - **Costo**: 2-3 días (Dockerfile, compose, timeouts).
   - **Estado**: observado pero no priorizado.

3. **Histoire en lugar de Storybook 10** (impacto bajo, costo bajo).
   - **Qué**: Histoire es Astro-native, usa Vite directamente sin cargar
     un segundo bundle manager.
   - **Beneficio**: setup más limpio, no requiere `@storybook/astro`
     adapter (que es un wrapper).
   - **Costo**: 1 día (migrar 23 stories actuales).
   - **Estado**: observado pero no priorizado.

### Descartadas (exploradas y rechazadas)

- **Next.js**: requiere bundle más grande, menos SSR-friendly para
  multi-tenant con workspaces. Astro 7 cubre el caso.
- **Remix**: similar a Next.js, menos comunidad LATAM, menos soporte
  para multi-tenant workspaces.
- **Pusher / Ably para realtime**: usamos Polling + Redis para stock;
  no necesitamos WebSockets todavía.
- **Kafka / RabbitMQ**: Redis queue es suficiente hasta ~10k jobs/min.
  Si llegamos ahí, reconsiderar.
- **Elasticsearch**: Postgres trigram + GIN es suficiente para búsqueda
  de productos hasta 100k SKUs. Migrar a Elasticsearch solo si llega a
  ser un cuello de botella medible.

## Cómo cambiar el stack (regla)

Antes de agregar o cambiar una pieza, responder:

1. **¿Qué problema concreto resuelve?** (no "es más moderno")
2. **¿Cuál es la alternativa más conservadora?** (preferirla si sirve)
3. **¿Cuánto cuesta migrar si revertimos?** (lock-in vs flexibilidad)
4. **¿Está alineado con multi-tenant SaaS o lo rompe?**
5. **¿Afecta al frontend y/o backend?**

Documentar la decisión acá (o en un sprint-specific doc) con las
respuestas, y solo entonces abrir un PR.
