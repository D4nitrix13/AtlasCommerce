# Docker

## Visión general

`Docker` gestiona todos los servicios del sistema.
En desarrollo se usa `docker-compose.dev.yml`.
En producción, se usa la configuración final de
`Docker Compose`.

## Servicios

### PostgreSQL (Base de datos)

```yaml
postgres:
  image: postgis/postgis:18-3.6
  container_name: ecommerce-postgres
  ports:
    - "${POSTGRES_PORT:-5432}:5432"
  volumes:
    - postgres_data:/var/lib/postgresql
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB"]
    interval: 10s
    timeout: 5s
    retries: 5
```

- **Imagen:** `PostGIS` 18-3.6
  (`PostgreSQL` + extensiones geoespaciales)
- **Puerto:** 5432 (interno, configurable vía
  `POSTGRES_PORT`)
- **Volúmenes:** Datos persistentes en `postgres_data`
- **Healthcheck:** Verifica disponibilidad cada 10s

### Redis (Cache + Colas)

```yaml
redis:
  image: redis:7-alpine
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
```

- **Imagen:** `Redis` 7 Alpine
- **Puerto:** 6379 (interno)
- **Propósito:** Cache, sesiones, colas de trabajos

### Backend (Laravel API)

```yaml
backend:
  build:
    context: ./backend/api
    dockerfile: Dockerfile
  ports:
    - "8000:8000"
  environment:
    - DB_CONNECTION=pgsql
    - DB_HOST=postgres
    - DB_PORT=5432
    - DB_DATABASE=${DB_DATABASE}
    - DB_USERNAME=${DB_USERNAME}
    - DB_PASSWORD=${DB_PASSWORD}
  depends_on:
    postgres:
      condition: service_healthy
```

- **Puerto:** 8000 (interno, servido por `Caddy`)
- **Dependencia:** `PostgreSQL` debe estar healthy

### Frontend (Astro)

```yaml
frontend:
  build:
    context: ./frontend/kitsune-store
    dockerfile: Dockerfile
  ports:
    - "4173:4173"
  environment:
    - PUBLIC_API_URL=${PUBLIC_API_URL}
```

- **Puerto:** 4173 (interno, servido por `Caddy`)
- **Build:** `pnpm build` genera archivos estáticos

### Caddy (Reverse Proxy)

```yaml
caddy:
  image: caddy:2-alpine
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - ./Caddyfile:/etc/caddy/Caddyfile
    - caddy_data:/data
    - caddy_config:/config
```

- **Puertos:** 80 y 443 (los únicos expuestos
  públicamente)
- **Volumes:** `Caddyfile`, datos TLS, configuración

## Volúmenes

- `postgres_data` — Datos de `PostgreSQL`
- `redis_data` — Datos de `Redis`
- `caddy_data` — Certificados TLS y cache de `Caddy`
- `caddy_config` — Configuración de `Caddy`

## Redes

Todos los servicios corren en una red `Docker` interna.
Solo `Caddy` tiene acceso externo.

```yaml
networks:
  internal:
    driver: bridge
```

## Variables de entorno

Las variables se cargan desde archivos `.env`:

- `docker/dev/.env` — Variables de desarrollo
- `backend/api/.env` — Variables del backend
- `frontend/.env` — Variables del frontend

Ver [environments.md](environments.md "Variables de entorno")
para la lista completa.

## Comandos

```bash
# Iniciar todos los servicios
docker compose -f docker-compose.dev.yml up -d

# Ver logs
docker compose logs -f

# Detener servicios
docker compose down

# Reconstruir un servicio
docker compose build backend
docker compose up -d backend

# Acceder a un contenedor
docker compose exec backend bash
docker compose exec postgres psql -U user -d database
```

## Estado actual

- **PostgreSQL** — Configurado en
  `docker/dev/docker-compose.dev.yml`
- **Redis** — Pendiente de agregar
- **Backend** — Pendiente de configurar `Dockerfile`
- **Frontend** — Pendiente de configurar `Dockerfile`
- **Caddy** — Pendiente de configurar `Caddyfile`
