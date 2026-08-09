# Despliegue General

## Infraestructura

El sistema se despliega en un VPS único con
`Docker` gestionando los servicios internos y
`Caddy` como reverse proxy con TLS automático.

```txt
┌─────────────────────────────────────────────────────┐
│                       VPS                           │
│                                                     │
│  ┌───────────────────────────────────────────────┐  │
│  │                   Caddy                       │  │
│  │           Reverse proxy + TLS (443)           │  │
│  │           Puerto 80 → redirect a 443          │  │
│  └──────┬────────────────┬────────────────┬──────┘  │
│         │                │                │         │
│         ▼                ▼                ▼         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │
│  │   Frontend  │  │ Admin Panel │  │ Backend API │  │
│  │   Astro 7   │  │  Astro 7    │  │ Laravel 13  │  │
│  │  :4173      │  │  :4173      │  │ :8000       │  │
│  └─────────────┘  └─────────────┘  └─────────────┘  │
│                                                     │
│  ┌───────────────────────────────────────────────┐  │
│  │            Servicios internos                 │  │
│  │  PostgreSQL :5432  ·  Redis :6379  · FTP :21  │  │
│  │  No expuestos a internet                      │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

## Dominios

- `panda-estampados.com` → Frontend `Astro`
  (storefront Panda) — `Caddy` → `Astro` :4173
- `kitsune.com` → Frontend `Astro`
  (storefront Kitsune) — `Caddy` → `Astro` :4173
- `admin.panda-estampados.com` → Panel
  administrativo — `Caddy` → `Astro` :4173
- `api.panda-estampados.com` → Backend
  `Laravel` API — `Caddy` → `Laravel` :8000

## Puertos expuestos

- **80** — TCP, `Caddy`, Público (redirect a 443)
- **443** — TCP, `Caddy`, Público (HTTPS)

## Puertos internos (NO expuestos)

- **4173** — `Astro` (frontend build)
- **8000** — `Laravel` API
- **5432** — `PostgreSQL`
- **6379** — `Redis`

## Red Docker

Todos los servicios corren en una red `Docker`
interna. Solo `Caddy` tiene acceso directo a los
puertos de Frontend y Backend. `PostgreSQL`,
`Redis` y Storage no son accesibles desde fuera
del VPS.

```yaml
# Red interna (implícita en Docker Compose)
services:
  caddy:
    ports:
      - "80:80"
      - "443:443"
    networks:
      - internal

  frontend:
    networks:
      - internal

  backend:
    networks:
      - internal

  postgres:
    networks:
      - internal

  redis:
    networks:
      - internal
```

## Flujo de una petición

```txt
1. Cliente visita https://kitsune.com/products
2. DNS resuelve al VPS
3. Caddy recibe petición en puerto 443
4. Caddy identifica dominio: kitsune.com
5. Caddy proxea a Frontend Astro (:4173)
6. Astro renderiza la página (SSG o SSR)
7. Frontend necesita datos → llama a API
8. GET https://api.panda-estampados.com/api/products?store_id=2
9. Caddy recibe petición API
10. Caddy proxea a Backend Laravel (:8000)
11. Laravel consulta PostgreSQL
12. Laravel retorna JSON
13. Frontend renderiza con los datos
14. Cliente recibe la página completa
```

## TLS / HTTPS

`Caddy` gestiona TLS automáticamente mediante
Let's Encrypt. No se necesita configuración
manual de certificados.

```caddyfile
# Ejemplo conceptual de Caddyfile
panda-estampados.com {
    reverse_proxy frontend:4173
}

kitsune.com {
    reverse_proxy frontend:4173
}

admin.panda-estampados.com {
    reverse_proxy frontend:4173
}

api.panda-estampados.com {
    reverse_proxy backend:8000
}
```

## Servicios Docker

- **postgres** — `postgis/postgis:18-3.6` — Base
  de datos `PostgreSQL` con `PostGIS`
- **redis** — `redis:7-alpine` — Cache, sesiones,
  colas
- **backend** — `laravel/laravel:13` — API
  `Laravel` (custom Dockerfile)
- **frontend** — `node:22-alpine` — build de
  `Astro` (custom Dockerfile)
- **caddy** — `caddy:2-alpine` — Reverse proxy
  con TLS
