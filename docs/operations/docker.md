# Docker — Multi-instance Layout

`Docker` gestiona todos los servicios del sistema. AtlasCommerce usa
**multi-instance layout**: cada instancia (par stage+store) tiene su
propio `docker-compose.yml` bajo `docker/instances/<stage>/<store>/`
y se opera exclusivamente vía
[`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh")
(NO `docker compose` directo — AGENTS.md raíz es explícito al
respecto).

Para topología de red y puertos, ver
[`operations/deployment.md`](deployment.md "deployment.md") § Topología.

## Estructura del repo

```text
docker/
├── COMPOSE.md                      # Convenciones + anatomía del stack
├── stack-orchestration/            # Defaults compartidos (incl profiles, etc)
└── instances/
    ├── development/
    │   ├── panda-estampados-and-kitsune/
    │   │   ├── docker-compose.yml
    │   │   └── environment/.env
    │   ├── mm-boutique/
    │   └── morales-store/
    ├── staging/                    # (cuando exista)
    └── production/                 # (cuando exista)
```

Cada instancia:

- Define su propio `.env` bajo `environment/`.
- Hereda defaults de `docker/stack-orchestration/`.
- Se opera via `scripts/docker-stack.sh <cmd> <stage> <store>`.

Para convenciones exactas de authoring (Dockerfile, entrypoints,
secrets, multi-stage builds) ver
[`docs/agent-skills/docker-stack-orchestration/SKILL.md`](../agent-skills/docker-stack-orchestration/SKILL.md "SKILL.md")
— es la regla canónica del proyecto.

## Servicios típicos (panda-estampados-and-kitsune dev)

### PostgreSQL + PostGIS (Base de datos)

```yaml
postgres:
  image: postgis/postgis:18-3.6
  # Mapped host port: 6432 -> container 5432
  # Volume: postgres-data (persistent)
  # Healthcheck: pg_isready cada 10s
```

- Imagen: `PostGIS` 18-3.6 (PostgreSQL + extensiones geoespaciales)
- Puerto: 5432 (interno), 6432 (host)
- Volumen: postgres-data (persiste entre up/down)

### Redis (Cache + Sesiones + Colas)

```yaml
redis:
  image: redis:7-alpine
  # Mapped host port: 7379 -> container 6379
  # Volume: redis-data
```

- Imagen: Redis 7 Alpine
- Puertos: 6379 (interno), 7379 (host)
- Propósito: cache, sesiones de Laravel, colas

### Backend (Laravel API + PHP-FPM)

```yaml
backend:
  build:
    context: ./backend
    dockerfile: Dockerfile
  # Mapped port: 8000 -> 8000
  environment:
    - DB_CONNECTION=pgsql
    - DB_HOST=postgres       # nombre de servicio en la red interna
    - DB_PORT=5432           # puerto INTERNO del container postgres
    - DB_DATABASE=...
```

- Puerto: 8000 (host) → 8000 (container)
- Build context: `./backend/` con su propio Dockerfile
- Rol: API REST + autenticación Sanctum + jobs + scheduler

### Frontend (Astro 7 SSR)

```yaml
frontend:
  build:
    context: ./frontend
    dockerfile: Dockerfile
  # Mapped port: 5321 -> 4321
  environment:
    - PUBLIC_API_URL=...
```

- Puerto: 5321 (host) → 4321 (container)
- Build: `pnpm build` genera el bundle final
- SSR: ver `frontend/astro.config.mjs` para adapter config

### Nginx (Reverse proxy de la instancia)

```yaml
nginx:
  image: nginx:1.27-alpine
  # Mapped ports: 9000 -> 8000
```

- Puerto: 9000 (host) → 8000 (container)
- Rutea `/api/*` al backend, todo lo demás al frontend
- Útil cuando no querés usar Caddy o para testing aislado

### Filebrowser + FTP + Mailpit + RedisInsight + pgAdmin

Servicios auxiliares comunes en dev:

| Servicio      | Puerto host | Propósito                                |
| ------------- | ----------- | ---------------------------------------- |
| filebrowser   | 9080        | Inspeccionar volumenes via UI web        |
| ftp           | 2121, 40000 | Subir assets grandes (smoke test storage)|
| mailpit       | 1025, 8025  | SMTP de testing + UI inbox               |
| pgadmin       | 6050        | Inspeccionar Postgres via UI web          |
| redis-insight | 6540        | Inspeccionar Redis via UI web            |

## Comandos (vía scripts/docker-stack.sh)

```bash
# Ver estado de servicios
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune

# Iniciar
./scripts/docker-stack.sh up dev panda-estampados-and-kitsune

# Iniciar incluyendo pgAdmin/redis-insight (profil dev-extra)
./scripts/docker-stack.sh up dev panda-estampados-and-kitsune --profile dev-extra

# Logs (todos los servicios)
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune -f

# Logs (un servicio específico)
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune backend -f

# Ejecutar comando en un container
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend bash

# Reiniciar un servicio
./scripts/docker-stack.sh restart dev panda-estampados-and-kitsune backend

# Parar (conserva volúmenes)
./scripts/docker-stack.sh down dev panda-estampados-and-kitsune

# Reset completo (¡BORRA DATOS!): down + eliminar volúmenes
./scripts/docker-stack.sh down dev panda-estampados-and-kitsune -v
```

## Variables de entorno

El `.env` de cada instancia vive en
`docker/instances/<stage>/<store>/environment/.env`. Cubre:

- Stack name / project name (afecta nombres de containers)
- Puertos host a mapear
- Credenciales de DB
- API keys externas (Sentry, OpenTelemetry, etc.)
- Stage (development/staging/production)

`backend/.env` y `frontend/.env` son archivos paralelos (config de
Laravel y Astro) — NO los pongas en docker/. Para detalle de cada
uno ver:

- [`environments.md`](environments.md "environments.md") — Vars backend
- [`operations/deployment.md`](deployment.md "deployment.md") § Variables

## Volúmenes

Patrón de naming: `<instance>-dev-<service>-data`. Sobreviven a
`down` y vuelven a usarse en el próximo `up`. Solo se borran con
`down -v`.

```bash
# Ver volúmenes de la instancia actual
docker volume ls | grep panda
```

## Redes

Cada instancia corre en su **propia** red Docker bridge (no compartida
entre instancias para garantizar aislamiento de tenants). Solo Nginx
(o Caddy global) tiene acceso externo según topología de deploy.

## Estado actual

- ✅ Multi-instance layout operativo (3 stores dev + 1 stage común)
- ✅ `scripts/docker-stack.sh` canónico + bash completion
- ✅ PostgreSQL/Redis/Backend/Frontend operativos
- ⚠️ Producción/staging aún no configurados (Fase 16+ pendiente)
- ⚠️ Caddyfile multi-tenant automatizado (parcial — ver SKILL
  caddyfile-multi-tenant-routing)
