# Despliegue en VPS

Topología de infraestructura + procedimiento operativo para
desplegar el sistema. Consolida el antiguo
`architecture/deployment-overview.md` (diagrama, dominios, flujo)
con el deploy operativo de VPS.

## Topología de infraestructura

El sistema se despliega como **multi-instance**: cada store corre en
su propio VPS (o contenedor con IP pública), gestionado por `Docker`
y `Caddy` (reverse proxy con TLS automático). Hay 3 instancias dev
y 3 instancias prod, una por store.

```text
┌─────────────────────────────────────────────────────────────────┐
│                      VPS Panda + Kitsune                        │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                       Caddy                               │  │
│  │           Reverse proxy + TLS (443)                       │  │
│  │           Puerto 80 → redirect a 443                      │  │
│  └──────┬──────────────────────┬──────────────────────────┘   │
│         │                      │                               │
│         ▼                      ▼                               │
│  ┌─────────────┐         ┌─────────────┐                       │
│  │  Frontend   │         │   Backend   │                       │
│  │  Astro 7    │         │  Laravel 13 │                       │
│  │  :4321      │         │  :8000      │                       │
│  └─────────────┘         └─────────────┘                       │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │            Servicios internos (red Docker privada)        │  │
│  │  PostgreSQL :5432 · Redis :6379 · FTP :21 (solo Panda)    │  │
│  │  No expuestos a internet                                 │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────┐   ┌─────────────────────────────┐
│        VPS MM Boutique      │   │     VPS Morales Store       │
│  (mismo layout, sin FTP     │   │  (mismo layout, sin FTP     │
│   expuesto, single-tenant)  │   │   expuesto, single-tenant)  │
└─────────────────────────────┘   └─────────────────────────────┘
```

### Dominios por tienda

- `panda-estampados.com` → Frontend `Astro` (storefront Panda)
  — `Caddy` → `Astro` `:4321`
- `kitsune.com` → Frontend `Astro` (storefront Kitsune) —
  `Caddy` → `Astro` `:4321`
- `admin.panda-estampados.com` → Panel administrativo —
  `Caddy` → `Astro` `:4321`
- `admin.kitsune.com` → Panel administrativo —
  `Caddy` → `Astro` `:4321`
- `mm-boutique.com` → Frontend `Astro` (storefront MM) —
  `Caddy` → `Astro` `:4321`
- `admin.mm-boutique.com` → Panel administrativo —
  `Caddy` → `Astro` `:4321`
- `morales-store.com` → Frontend `Astro` (storefront Morales) —
  `Caddy` → `Astro` `:4321`
- `admin.morales-store.com` → Panel administrativo —
  `Caddy` → `Astro` `:4321`
- `api.<store>.com` → Backend `Laravel` API — `Caddy` → `:8000`

> **Convención de dominios:** cada store tiene su propio subdominio
> `api.*` para la API, opcional. En dev local se rutea todo por
> path (`/api/*`) sobre el mismo host. Ver
> [`operations/caddy.md`](caddy.md "operations/caddy.md") § Config.

### Puertos expuestos (públicos)

- **80** — TCP, `Caddy`, redirect a 443
- **443** — TCP, `Caddy`, HTTPS

### Puertos internos (no expuestos)

- **4321** — `Astro` (frontend SSR)
- **8000** — `Laravel` API
- **5432** — `PostgreSQL`
- **6379** — `Redis`
- **21** — `FTP` (solo en `panda-estampados-and-kitsune`)

### Red Docker

Todos los servicios corren en una red Docker interna. Solo `Caddy`
tiene acceso directo a los puertos de Frontend y Backend.
`PostgreSQL`, `Redis` y Storage no son accesibles desde fuera
del VPS.

```yaml
# Red interna (definida en cada compose de instancia)
services:
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
1. Cliente visita https://panda-estampados.com/products
2. DNS resuelve al VPS de Panda + Kitsune
3. Caddy recibe petición en puerto 443
4. Caddy identifica dominio: panda-estampados.com
5. Caddy proxea a Frontend Astro (:4321)
6. Astro renderiza la página (SSR)
7. Frontend necesita datos → llama a API
8. GET https://api.panda-estampados.com/api/v1/products
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
# Ejemplo conceptual de Caddyfile por instancia
panda-estampados.com {
    reverse_proxy frontend:4321
}

kitsune.com {
    reverse_proxy frontend:4321
}

admin.panda-estampados.com {
    reverse_proxy frontend:4321
}

api.panda-estampados.com {
    reverse_proxy backend:8000
}
```

Para el setup completo de Caddy (multi-tenant routing, producción),
ver [`operations/caddy.md`](caddy.md "operations/caddy.md").

## Servicios Docker

- **postgres** — `postgis/postgis:18-3.6` — Base de datos
  `PostgreSQL` con `PostGIS`
- **redis** — `redis:7-alpine` — Cache, sesiones, colas
- **backend** (dev) / **app** (prod) — Laravel 13 — API PHP-FPM
  (custom Dockerfile)
- **frontend** — `node:22-alpine` — build de `Astro` (custom
  Dockerfile)
- **caddy** — `caddy:2-alpine` — Reverse proxy con TLS

> **Convención:** los nombres de servicio cambian entre dev y prod.
> En dev se llaman `backend`/`frontend`/`postgres`/`redis`. En
> prod se llaman `app`/`frontend`/`db`/`redis`. El dispatcher
> `scripts/docker-stack.sh` lo abstrae — pasás `<stage> <store>`
> y resolvés el servicio sin importar el alias.

Para detalle de la orquestación multi-instance (dev/prod con
`scripts/docker-stack.sh`), ver
[`SKILL.md`](../agent-skills/docker-stack-orchestration/SKILL.md "SKILL.md").

## Procedimiento de deploy en VPS

### 1. Preparar el VPS

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Instalar Docker Compose (plugin v2)
sudo apt install docker-compose-plugin -y

# Instalar Git + utilidades
sudo apt install git jq -y
```

### 2. Clonar el repositorio

```bash
sudo mkdir -p /opt/atlas-commerce
sudo chown $USER:$USER /opt/atlas-commerce
cd /opt/atlas-commerce
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce
```

### 3. Configurar variables de entorno

Por cada instancia que se quiera levantar, se configura su propio
`.env` bajo `docker/instances/<stage>/<store>/environment/.env`.
Tambien se copian los `.env` del backend y frontend (no están en
git):

```bash
# Backend (Laravel) — config de aplicación
cp backend/.env.example backend/.env
nano backend/.env

# Frontend (Astro) — config de aplicación
cp frontend/.env.example frontend/.env
nano frontend/.env

# Docker de la instancia — compose overrides + puertos + secrets
cp docker/instances/production/<store>/environment/.env.example \
   docker/instances/production/<store>/environment/.env
nano docker/instances/production/<store>/environment/.env
```

> **Convencional entrypoint**: todos los comandos de
> `docker compose` deben ir a través de
> [`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh")
> con `<cmd> <stage> <store>`, no usar `docker compose` directo.
> Ver AGENTS.md raíz.

### 4. Configurar DNS

Configurar los registros `A` en el proveedor de dominios (un VPS
por instancia, IPs distintas según la store). Ver
[`stack/infrastructure.md`](../stack/infrastructure.md "stack/infrastructure.md")
§ Configuración DNS para la tabla completa.

### 5. Iniciar servicios

```bash
# Usar el dispatcher canonico (no docker compose directo)
./scripts/docker-stack.sh up production panda-estampados-and-kitsune
```

### 6. Ejecutar migraciones

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune \
    backend php artisan migrate --force
```

### 7. Sembrar datos iniciales (opcional)

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune \
    backend php artisan db:seed --force
```

### 8. Build del frontend

```bash
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune \
    frontend pnpm build
```

### 9. Configurar Caddy (per-store Caddyfile)

```bash
nano docker/instances/production/<store>/Caddyfile
./scripts/docker-stack.sh restart production <store> frontend
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
curl -I https://mm-boutique.com
curl -I https://morales-store.com
```

## Despliegue de actualizaciones

```bash
cd /opt/atlas-commerce/AtlasCommerce
git pull

./scripts/docker-stack.sh exec production panda-estampados-and-kitsune \
    backend php artisan migrate --force

./scripts/docker-stack.sh exec production panda-estampados-and-kitsune \
    frontend pnpm build

./scripts/docker-stack.sh restart production panda-estampados-and-kitsune
```

## Verificación post-despliegue

- **Servicios Docker** —
  `./scripts/docker-stack.sh status production <store>`
- **Logs sin errores** —
  `./scripts/docker-stack.sh logs production <store>`
- **API responde** —
  `curl https://api.panda-estampados.com/api/v1/products`
- **TLS funciona** —
  `curl -I https://panda-estampados.com`
- **Admin accesible** — Abrir `admin.<dominio>.com`

## Multi-instance y rollback

Para setups con múltiples instancias (dev, prod, per-tenant), todo
el workflow se ejecuta a través de `scripts/docker-stack.sh` con
el par `<stage> <store>`. Cada instancia tiene su `.env` propio
y su directorio `docker/instances/<stage>/<store>/`.

El rollback debe hacerse vía `git revert` + redeploy con el commit
anterior (no hay estrategia de release artifacts en este momento).

Para respuesta a incidentes durante deploy (services down, errores
de migración, etc.), ver
[`operations/observability-runbook.md`](observability-runbook.md "observability-runbook.md").
