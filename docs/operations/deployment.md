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
> [`getting-started/caddy.md`](../getting-started/caddy.md "caddy.md") § Config.

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
ver [`getting-started/caddy.md`](../getting-started/caddy.md "caddy.md").

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
[`architecture/tech-infrastructure.md`](../architecture/tech-infrastructure.md "architecture/tech-infrastructure.md")
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
[`operations/observability.md`](observability.md "operations/observability.md").

## Deploy Multi-Instance — Workflow por Instancia

> Guía paso a paso para deployar una instancia del sistema en un
> servidor nuevo. Asume que ya tenés el repo clonado y un servidor
> con Docker instalado.

---

## Vista general

Cada instancia se deploya independientemente:

| Instancia                                | Servidor               | Tiendas                       |
| ---------------------------------------- | ---------------------- | ----------------------------- |
| `panda-estampados-and-kitsune/` (prod)   | Server A (multi-tenant)| Panda + Kitsune               |
| `mm-boutique/` (prod)                    | Server B (single-tenant)| MM Boutique                  |
| `morales-store/` (prod)                  | Server C (single-tenant)| Morales Store                |

Cada servidor corre un único stack Docker con la instancia
correspondiente. La base de código es la misma; lo que cambia es
el `environment/.env`, el `Caddyfile` y los `seeds/*.json`.

> **Convencional entrypoint:** toda la operatoria va por
> [`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh"),
> NO usar `docker compose` directo. Ver AGENTS.md raíz.

---

## Pre-requisitos del servidor

```bash
# Docker Engine + Compose plugin v2
sudo apt install docker.io docker-compose-plugin

# Git + utilidades
sudo apt install git curl jq vim

# Verificar versiones
docker --version          # >= 24.x
docker compose version    # >= 2.x (plugin)
git --version
```

Puertos expuestos al público (uno por servidor):

| Puerto | Servicio | Caddy                                       |
| ------ | -------- | ------------------------------------------- |
| 80     | HTTP     | sí (redirect a HTTPS en prod)               |
| 443    | HTTPS    | sí (certificados Let's Encrypt automáticos) |

Puertos internos (no expuestos al público) — nombres de servicio
**varían entre dev y prod**:

| Container port | Servicio             | Dev service name | Prod service name |
| -------------- | -------------------- | ---------------- | ----------------- |
| 8000           | Backend Laravel      | `backend`        | `app`             |
| 4321           | Frontend Astro       | `frontend`       | `frontend`        |
| 5432           | PostgreSQL           | `postgres`       | `db`              |
| 6379           | Redis                | `redis`          | `redis`           |

El dispatcher `scripts/docker-stack.sh` abstrae la diferencia:
siempre pasás `<stage> <store>` y un nombre de servicio canónico
(`backend`/`frontend`/`postgres`/`redis`); el script traduce a
`app`/`db` si el stage es `production`.

---

## Workflow de deploy — primera vez

### 1. Clonar el repo

```bash
sudo mkdir -p /opt/atlas-commerce
sudo chown $USER:$USER /opt/atlas-commerce
cd /opt/atlas-commerce
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce
```

### 2. Configurar la instancia

```bash
cd docker/instances/production/<store>   # ej: mm-boutique

# Copiar template de variables de entorno
cp environment/.env.example environment/.env

# Editar .env: cambiar TODOS los valores CAMBIAR_*
$EDITOR environment/.env
```

Valores críticos a cambiar en `environment/.env`:

```env
APP_KEY=                     # Generar con:
                             #   ./scripts/docker-stack.sh exec production <store> \
                             #       app php artisan key:generate --show
DB_PASSWORD=                 # Password seguro de Postgres
<STORE>_ADMIN_PASSWORD=      # Password del admin de la tienda.
                             # Variable según instancia:
                             #   PANDA_ADMIN_PASSWORD | MM_ADMIN_PASSWORD
                             #   MORALES_ADMIN_PASSWORD
```

> **Sobre `<STORE>_ADMIN_PASSWORD`:** el entrypoint de prod
> (`backend/entrypoint.production.sh`) lee esta variable al
> inicializar la BD para crear el admin user del seed. El sufijo
> `<STORE>` depende de la instancia:
> `PANDA_ADMIN_PASSWORD` para `panda-estampados-and-kitsune`,
> `MM_ADMIN_PASSWORD` para `mm-boutique`,
> `MORALES_ADMIN_PASSWORD` para `morales-store`.

Generar `APP_KEY`:

```bash
# Levantar solo el backend momentáneamente para generar la key.
# Antes hay que tener el .env configurado (DB_PASSWORD al menos).
./scripts/docker-stack.sh up production <store> backend
./scripts/docker-stack.sh exec production <store> backend \
    php artisan key:generate --show
# → base64:XYZ123... (copiar este valor a APP_KEY en .env)
```

### 3. Levantar los servicios

```bash
./scripts/docker-stack.sh up production <store>
```

El primer arranque tarda más (build de imágenes). El entrypoint:

1. Espera a que Postgres esté listo.
2. Genera `APP_KEY` si no existe.
3. Corre migraciones.
4. Corre seeders (currencies + permissions).
5. Corre `tenants:bootstrap` con el JSON de `seeds/`.
6. Arranca PHP-FPM.

Ver el progreso:

```bash
./scripts/docker-stack.sh logs production <store> backend -f
```

### 4. Verificar

```bash
# Estado de los servicios
./scripts/docker-stack.sh status production <store>

# Health check del backend (dentro del container backend)
./scripts/docker-stack.sh exec production <store> backend \
    curl -I http://localhost:8000/up
# HTTP/1.1 200 OK

# Health check del frontend (dentro del container frontend)
./scripts/docker-stack.sh exec production <store> frontend \
    curl -I http://localhost:4321/
# HTTP/1.1 302 Found (redirect a /es)

# Health check de Caddy (desde el host)
curl -I http://localhost/
# HTTP/1.1 200 OK
```

> **Nota sobre los puertos `8000` y `4321`:** estos son los puertos
> **internos de los containers** (donde Laravel y Astro escuchan
> dentro de la red de docker). Las instancias de prod **no
> publican estos puertos al host**; el tráfico HTTP entra por
> Caddy vía dominio (`panda-estampados.com`, etc.). Los `curl` de
> arriba se ejecutan **dentro del container** vía
> `scripts/docker-stack.sh exec`. Ver
> [`docs/operations/ports.md`](../operations/ports.md "operations/ports.md")
> para el mapa completo.

### 5. Configurar DNS

Para cada dominio de la instancia, apuntar al servidor:

```dns
# Ejemplo para mm-boutique.com
A   mm-boutique.com         <IP-SERVER>
A   www.mm-boutique.com     <IP-SERVER>
A   admin.mm-boutique.com   <IP-SERVER>
```

En el registrador de dominios (Cloudflare, Namecheap, etc.),
agregar estos registros A.

---

## Workflow de deploy — actualizaciones

```bash
ssh usuario@servidor
cd /opt/atlas-commerce/AtlasCommerce

# Traer últimos cambios
git pull

# Rebuild + restart (la instancia se apaga y se vuelve a levantar)
./scripts/docker-stack.sh up production <store>
```

Si hay migraciones nuevas:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan migrate --force
```

Si hay cambios en `seeds/*.json`:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan tenants:bootstrap seeds/<archivo>.json
```

---

## HTTPS con Let's Encrypt (producción)

En el `Caddyfile` de cada instancia, **quitar** la línea `auto_https off`
y dejar que Caddy gestione los certificados automáticamente:

```caddyfile
{
    email ops@example.com   # Email para Let's Encrypt
}

https:// {
    @hostnames host mm-boutique.com www.mm-boutique.com \
                       admin.mm-boutique.com

    handle @hostnames {
        @api path /api/*
        handle @api {
            reverse_proxy app:8000
        }
        handle {
            reverse_proxy frontend:4321
        }
    }

    handle {
        respond "Not found" 404
    }
}
```

> **Nombre de servicio:** en prod Caddy habla con `app` (no
> `backend`). El compose de prod define el servicio PHP-FPM como
> `app` — ver `docker/instances/production/<store>/docker-compose.yml`.

Caddy automáticamente:

- Obtiene certificados de Let's Encrypt vía HTTP-01 challenge.
- Los renueva antes de expirar.
- Redirige HTTP a HTTPS.

Para que Let's Encrypt emita certificados, **el puerto 80 debe
estar abierto al público** y el dominio debe apuntar al servidor.

DNS-01 challenge (alternativa para wildcards):

Si usás DNS-01, agregás el módulo de tu proveedor en el Caddyfile:

```caddyfile
{
    email ops@example.com
    acme_dns cloudflare {env.CLOUDFLARE_API_TOKEN}
}
```

---

## Backups

### Automático con cron

Cada servidor tiene su propio cron job que dumpa la BD. Forma
canónica: usar el subcomando `db-backup` del dispatcher (que ya
escribe a `db-backups/<stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz`):

```bash
# /etc/cron.d/atlas-commerce-backup
0 3 * * * root cd /opt/atlas-commerce/AtlasCommerce \
  && /opt/atlas-commerce/AtlasCommerce/scripts/docker-stack.sh \
       db-backup production <store> \
  >> /var/log/atlas-commerce-backup.log 2>&1
```

> **Sobre `db-backup`:** corre `pg_dump` dentro del container
> postgres del tenant y deja el archivo en `db-backups/` (relativo
> a la raíz del repo). Para subirlo a S3 / Drive, agregar `aws s3
> cp` o `rclone copy` al final del cron. Detalle completo en
> [`operations/backups.md`](../operations/backups.md "operations/backups.md").

### Restore

Forma canónica:

```bash
./scripts/docker-stack.sh db-restore production <store> \
    db-backups/<archivo>.sql.gz
```

Forma manual (equivalente, NO recomendado):

```bash
# Bajar el último backup
aws s3 cp s3://<bucket>-backups/<store>/<timestamp>.sql.gz /tmp/

# Restaurar vía pg_restore del container postgres del tenant
gunzip < /tmp/<file>.sql.gz | \
    /usr/bin/docker exec -i <store>-prod-db \
        psql -U postgres -d <store_slug>
```

Retención: 30 días mínimo. Configurar lifecycle en S3 para borrar
automáticamente los backups más viejos.

---

## Monitoreo

Mínimo viable:

- **Logs centralizados**: Loki + Grafana o servicio equivalente.
- **Uptime**: UptimeRobot, Betterstack, etc.
- **Métricas**: Prometheus + Grafana (opcional, para VPS grandes).

Para setup de monitoreo per-instance con Prometheus, ver
[`operations/observability.md`](../operations/observability.md "operations/observability.md").

---

## Troubleshooting

### El primer arranque falla con "connection refused" a Postgres

El entrypoint reintenta cada 2 segundos, pero si tarda más:

```bash
./scripts/docker-stack.sh logs production <store> postgres
# Verificar que el container de DB está healthy
./scripts/docker-stack.sh status production <store>
```

### El container `backend` reinicia constantemente (prod)

```bash
./scripts/docker-stack.sh logs production <store> backend --tail 100
```

Causas comunes:

- `APP_KEY` no seteada → el entrypoint la genera, pero si el
  `.env` está mal, falla.
- `DB_PASSWORD` no coincide con el del `docker-compose.yml`.
- `tenants:bootstrap` falla por JSON malformado. Verificar que el
  seed es JSON válido:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    cat /tmp/seeds/<file>.json | jq .
```

### 502 Bad Gateway desde Caddy

El backend no está respondiendo:

```bash
./scripts/docker-stack.sh logs production <store> backend
./scripts/docker-stack.sh exec production <store> backend \
    curl -I http://localhost:8000/up
```

> El `curl` apunta al `:8000` interno del container. Se ejecuta
> desde dentro del container via `exec`.

Si Caddy arranca antes que el backend, va a fallar. El `depends_on`
con `condition: service_healthy` debería prevenir esto, pero si el
healthcheck del backend falla, hay que revisarlo.

### El admin user no puede loguearse

El password puede no haberse cargado bien:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan tinker --execute='
$user = App\Models\Auth\User::where("email", "admin@example.com")->first();
echo $user ? "OK" : "NOT FOUND";
'
```

Si existe pero el password no funciona, verificar que la variable
`<STORE>_ADMIN_PASSWORD` (ej. `PANDA_ADMIN_PASSWORD`,
`MM_ADMIN_PASSWORD`, `MORALES_ADMIN_PASSWORD`) está bien seteada
en `environment/.env`.

### Migración falla

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan migrate --pretend
# Ver el SQL que se va a correr

./scripts/docker-stack.sh exec production <store> backend \
    php artisan migrate --force
# Forzar la corrida
```

Si está atascada en una migración:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan migrate:rollback --step=1
# Rollback de la última migración
```

### Quiero agregar una tienda nueva a una instancia existente

Por ejemplo, agregar Morales Store a la instancia de MM Boutique
(que tiene 1 tienda). Editar `seeds/mm-boutique.json`:

```json
{
  "tenants": [
    { "slug": "mm-boutique", ... },
    { "slug": "morales-store", ... }
  ],
  ...
}
```

Correr:

```bash
./scripts/docker-stack.sh exec production <store> backend \
    php artisan tenants:bootstrap seeds/mm-boutique.json
```

El comando es idempotente: agrega Morales Store sin tocar MM
Boutique.

---

## Setup inicial del DNS — checklist

```txt
[ ] Comprar dominios (o usar subdominios de uno existente).
[ ] Configurar registros A apuntando a la IP del servidor.
[ ] Esperar propagación DNS (puede tardar hasta 48h).
[ ] Verificar con `dig mm-boutique.com` que apunta al servidor.
[ ] Habilitar HTTPS en Caddy (quitar auto_https off).
[ ] Verificar que Let's Encrypt emite el certificado.
[ ] Verificar que el sitio carga con HTTPS.
[ ] Configurar HSTS en Caddy para forzar HTTPS siempre.
```

## Resumen del workflow

```mermaid
flowchart TD
    A[Comprar dominio + apuntar DNS] --> B[Setup servidor: Docker, Git]
    B --> C[git clone AtlasCommerce]
    C --> D[cd docker/instances/production/<store>]
    D --> E[cp environment/.env.example environment/.env]
    E --> F[Editar .env: passwords, APP_KEY]
    F --> G[./scripts/docker-stack.sh up production <store>]
    G --> H{¿Primer arranque?}
    H -->|Sí| I[entrypoint: migrate + seed + tenants:bootstrap]
    H -->|No| J[entrypoint: solo migrate]
    I --> K[Verificar: status, exec curl health]
    J --> K
    K --> L[Configurar HTTPS + Let's Encrypt]
    L --> M[Setup backups: cron + S3]
    M --> N[Listo para producción]
```

## Documentación relacionada

- [`docker/instances/README.md`](../../docker/instances/README.md) — Índice
  de instancias.
- [`multi-instance.md`](../architecture/multi-instance.md) —
  Roadmap completo.
- [`linting.md`](../development/linting.md) — Linters del proyecto.
- [`installation.md`](../getting-started/installation.md) — Setup de dev local.
- [Caddy docs](https://caddyserver.com/docs/) — Configuración de Caddy.
- [Docker Compose docs](https://docs.docker.com/compose/) — Referencia de compose.
