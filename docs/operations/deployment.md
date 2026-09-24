# Despliegue en VPS

Topología de infraestructura + procedimiento operativo para
desplegar el sistema. Consolida el antiguo
`architecture/deployment-overview.md` (diagrama, dominios, flujo)
con el deploy operativo de VPS.

## Topología de infraestructura

El sistema se despliega en un VPS único con `Docker` gestionando
los servicios internos y `Caddy` como reverse proxy con TLS
automático.

```text
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

### Dominios por tienda

- `panda-estampados.com` → Frontend `Astro` (storefront Panda)
  — `Caddy` → `Astro` :4173
- `kitsune.com` → Frontend `Astro` (storefront Kitsune) —
  `Caddy` → `Astro` :4173
- `admin.panda-estampados.com` → Panel administrativo —
  `Caddy` → `Astro` :4173
- `api.panda-estampados.com` → Backend `Laravel` API —
  `Caddy` → `Laravel` :8000

### Puertos expuestos (públicos)

- **80** — TCP, `Caddy`, redirect a 443
- **443** — TCP, `Caddy`, HTTPS

### Puertos internos (no expuestos)

- **4173** — `Astro` (frontend build)
- **8000** — `Laravel` API
- **5432** — `PostgreSQL`
- **6379** — `Redis`

### Red Docker

Todos los servicios corren en una red Docker interna. Solo `Caddy`
tiene acceso directo a los puertos de Frontend y Backend.
`PostgreSQL`, `Redis` y Storage no son accesibles desde fuera
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

```text
1. Cliente visita https://kitsune.com/products
2. DNS resuelve al VPS
3. Caddy recibe petición en puerto 443
4. Caddy identifica dominio: kitsune.com
5. Caddy proxea a Frontend Astro (:4173)
6. Astro renderiza la página (SSG o SSR)
7. Frontend necesita datos → llama a API
8. GET https://api.panda-estampados.com/api/v1/products?store_id=2
9. Caddy recibe petición API
10. Caddy proxea a Backend Laravel (:8000)
11. Laravel consulta PostgreSQL
12. Laravel retorna JSON
13. Frontend renderiza con los datos
14. Cliente recibe la página completa
```

## TLS / HTTPS

`Caddy` gestiona TLS automáticamente mediante Let's Encrypt. No
se necesita configuración manual de certificados.

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

Para el setup completo de Caddy (multi-tenant routing, producción),
ver `operations/caddy.md` (después del merge con
`getting-started/caddy.md`).

## Servicios Docker

- **postgres** — `postgis/postgis:18-3.6` — Base de datos
  `PostgreSQL` con `PostGIS`
- **redis** — `redis:7-alpine` — Cache, sesiones, colas
- **backend** — Laravel 13 — API PHP-FPM (custom Dockerfile)
- **frontend** — `node:22-alpine` — build de `Astro` (custom
  Dockerfile)
- **caddy** — `caddy:2-alpine` — Reverse proxy con TLS

Para detalle de la orquestación multi-instance (dev/staging/prod
con `scripts/docker-stack.sh`), ver
[`docs/agent-skills/docker-stack-orchestration/SKILL.md`](../agent-skills/docker-stack-orchestration/SKILL.md "SKILL.md")
y
[`docs/development/ai-tooling.md`](../development/ai-tooling.md "ai-tooling.md")
(este último documenta el setup de tooling IA asociado al
workflow de deploy).

## Procedimiento de deploy en VPS

### 1. Preparar el VPS

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Instalar Docker Compose
sudo apt install docker-compose-plugin -y

# Instalar Git
sudo apt install git -y
```

### 2. Clonar el repositorio

```bash
cd /var/www
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce
```

### 3. Configurar variables de entorno

Por cada instancia que se quiera levantar (multi-instance), se
configura su propio `.env` bajo
`docker/instances/development/<tenant>/environment/.env`:

```bash
# Backend (mismo .env que en local)
cp backend/.env.example backend/.env
nano backend/.env

# Frontend
cp frontend/.env.example frontend/.env
nano frontend/.env

# Docker (por instancia)
cp docker/instances/development/<tenant>/environment/.env.example \
   docker/instances/development/<tenant>/environment/.env
nano docker/instances/development/<tenant>/environment/.env
```

> **Convencional entrypoint**: todos los comandos de
> `docker compose` deben ir a través de
> [`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh")
> con `up <stage> <store>`, no usar `docker compose` directo.
> Ver AGENTS.md raíz.

### 4. Configurar DNS

Configurar los registros DNS en el proveedor de dominios:

- **A `@`** — IP del VPS
- **A `www`** — IP del VPS
- **A `api`** — IP del VPS
- **A `admin`** — IP del VPS
- **A `kitsune`** — IP del VPS

### 5. Iniciar servicios

```bash
# Usar el dispatcher canonico (no docker compose directo)
./scripts/docker-stack.sh up production panda-estampados-and-kitsune
```

### 6. Ejecutar migraciones

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune backend \
  php artisan migrate --force
```

### 7. Sembrar datos iniciales (opcional)

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune backend \
  php artisan db:seed --force
```

### 8. Build del frontend

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune frontend \
  pnpm build
```

### 9. Configurar Caddy (per-store Caddyfile)

```bash
nano docker/Caddyfile
./scripts/docker-stack.sh restart production panda-estampados-and-kitsune caddy
```

### 10. Verificar

```bash
# Estado de contenedores
./scripts/docker-stack.sh status production panda-estampados-and-kitsune

# Logs en vivo
./scripts/docker-stack.sh logs production panda-estampados-and-kitsune

# Smoke tests
curl -I https://api.panda-estampados.com/api/v1/up
curl -I https://panda-estampados.com
curl -I https://kitsune.com
curl -I https://admin.panda-estampados.com
```

## Despliegue de actualizaciones

```bash
cd /var/www/AtlasCommerce
git pull

./scripts/docker-stack.sh exec production panda-estampados-and-kitsune backend \
  php artisan migrate --force

./scripts/docker-stack.sh exec production panda-estampados-and-kitsune frontend \
  pnpm build

./scripts/docker-stack.sh restart production panda-estampados-and-kitsune backend frontend
```

## Verificación post-despliegue

- **Servicios Docker** —
  `./scripts/docker-stack.sh status production <store>`
- **Logs sin errores** —
  `./scripts/docker-stack.sh logs production <store> --tail=50`
- **API responde** —
  `curl https://api.panda-estampados.com/api/v1/products`
- **TLS funciona** —
  `curl -I https://panda-estampados.com`
- **Admin accesible** — Abrir `admin.panda-estampados.com`

## Multi-instance y rollback

Para setups con múltiples instancias (dev, staging, prod, per-tenant),
todo el workflow se ejecuta a través de `scripts/docker-stack.sh`
con el par `<stage> <store>`. Cada instancia tiene su `.env` propio
y su directorio `docker/instances/<stage>/<store>/`.

El rollback debe hacerse vía `git revert` + redeploy con el commit
anterior (no hay estrategia de release artifacts en este momento).

Para respuesta a incidentes durante deploy (services down, errores
de migración, etc.), ver el runbook de Fase 13 cuando esté
publicado en `docs/runbooks/`.
