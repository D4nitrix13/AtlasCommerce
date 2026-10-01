# AtlasCommerce

Plataforma multi-tienda para la gestión y comercialización de merchandising
personalizado, productos de cultura japonesa, videojuegos, anime, artículos
textiles, cerámica, vidrio, madera, aluminio y regalos corporativos. Construida
sobre Laravel 13 + Astro 7, pensada para correr múltiples tiendas (Panda
Estampados, Kitsune, MM Boutique, Morales Store) sobre una base de código
compartida con resolución por dominio.

## Descripción

La plataforma funciona como:

- **Vitrina digital** — catálogo público de productos con precios, disponibilidad
  e interacción por WhatsApp.
- **Panel administrativo** — gestión de clientes, productos, categorías,
  proveedores, compras, facturas, reportes, respaldos, auditoría, logs y
  notificaciones internas.

Soporta **multi-tienda** con resolución por dominio:

| Tienda | Dominio | Enfoque |
| --- | --- | --- |
| **Panda Estampados** | `panda-estampados.com` | B2B / corporativo — estampados personalizados |
| **Kitsune Store** | `kitsune.com` | B2C — cultura anime, manga y japonesa |
| **MM Boutique** | `mm-boutique.com` | Single-tenant |
| **Morales Store** | `morales-store.com` | Single-tenant |

Las 4 tiendas se ejecutan como **instancias Docker independientes** que
comparten código y configuración, pero tienen su propia DB, sus volúmenes y
su dominio.

## Stack Tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.4, Laravel 13.8, Sanctum |
| Frontend | Astro 7, React 19, Tailwind CSS 4, TypeScript |
| Base de datos | PostgreSQL 18 + PostGIS |
| Cache / Colas | Redis 7 |
| Storage | Volúmenes Docker (bind mount en dev) |
| Reverse proxy | Caddy |
| Infraestructura | Docker Compose v2 |
| Package manager | Composer (PHP), pnpm (Node) |
| Testing | PHPUnit 12 |

## Estructura del Proyecto

```txt
AtlasCommerce/
├── backend/                  # API REST — Laravel 13
│   ├── app/                  # Lógica de aplicación
│   ├── database/             # Migraciones, seeds, factories
│   ├── packages/             # Plugins Composer locales (path repos)
│   └── ...
├── frontend/                 # Interfaz web — Astro 7 + React 19
│   ├── src/
│   └── ...
├── docker/
│   ├── dev/                  # Compose + env del entorno de desarrollo
│   ├── instances/            # Compose por instancia de prod (1 carpeta c/u)
│   │   ├── panda-estampados/    # Multi-tenant (Panda + Kitsune)
│   │   ├── mm-boutique/      # Single-tenant
│   │   └── morales-store/    # Single-tenant
│   ├── conf/                 # Configs montados (postgres, redis, php, etc.)
│   ├── Dockerfile.backend    # Imagen backend producción
│   ├── Dockerfile.backend.dev # Imagen backend dev (xdebug + bind mounts)
│   ├── Dockerfile.frontend   # Imagen frontend producción
│   ├── Dockerfile.frontend.dev # Imagen frontend dev
│   ├── entrypoint-backend*.sh
│   └── versions.env          # Pinea versiones de imágenes
├── database/                 # Migraciones iniciales, seeds, init SQL
├── docs/                     # Documentación técnica y de diseño
│   ├── architecture/
│   ├── backend/
│   ├── frontend/
│   ├── deployment/
│   ├── operations/           # Runbooks, puertos, etc.
│   └── ...
├── scripts/
│   ├── docker-stack.sh       # Orquestador de las 4 instancias
│   └── setup-local.sh
└── Caddyfile                 # Configuración Caddy (prod)
```

## Requisitos

- Docker Engine 24+ con Compose v2
- Bash 4+ (para los scripts)
- Node.js 20+ y pnpm 9+ (solo si vas a tocar el frontend fuera de Docker)
- PHP 8.4+ (solo si vas a tocar el backend fuera de Docker)

## Instalación

La primera vez, el orquestador `docker-stack.sh` se encarga de crear los
`.env` desde los `.env.example`, generar `docker/versions.env` y levantar
todo.

```bash
# 1. Levantar todo (prod + dev si docker/dev/.env existe):
./scripts/docker-stack.sh setup

# 2. Agregar los hosts al /etc/hosts (para que los dominios resuelvan local):
sudo ./scripts/docker-stack.sh hosts >> /etc/hosts

# 3. (Opcional) Instalar el Caddyfile del repo:
sudo cp Caddyfile /etc/caddy/Caddyfile
sudo systemctl restart caddy

# 4. Verificar:
curl -sI http://panda-estampados.com/api/storefront/theme
```

### Sólo el entorno de desarrollo

Si solo querés dev (sin las 3 instancias de prod):

```bash
cp docker/dev/.env.example docker/dev/.env
$EDITOR docker/dev/.env   # completá POSTGRES_PASSWORD, FTP_PASSWORD, etc.

./scripts/docker-stack.sh setup dev
```

### Destruir y empezar de cero

```bash
./scripts/docker-stack.sh nuke all   # ⚠ borra DB y volúmenes
./scripts/docker-stack.sh setup
```

## Comandos útiles del orquestador

```bash
./scripts/docker-stack.sh help                       # todos los comandos
./scripts/docker-stack.sh start dev                  # levantar solo dev
./scripts/docker-stack.sh start panda-estampados        # levantar solo Panda
./scripts/docker-stack.sh stop all                   # detener todas
./scripts/docker-stack.sh restart dev backend        # reiniciar un servicio
./scripts/docker-stack.sh build dev                  # rebuild (no cache)
./scripts/docker-stack.sh logs dev backend           # tail logs
./scripts/docker-stack.sh exec dev backend bash      # shell dentro de un container
./scripts/docker-stack.sh status all                 # estado de servicios
./scripts/docker-stack.sh ps                         # containers corriendo
./scripts/docker-stack.sh down dev                   # bajar containers (preserva datos)
./scripts/docker-stack.sh nuke dev                   # ⚠ bajar + borrar volúmenes
```

## Comandos de desarrollo (dentro de los containers)

### Backend

```bash
./scripts/docker-stack.sh exec dev backend bash
# ya dentro del container:
php artisan migrate
php artisan db:seed
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse
```

### Frontend

```bash
./scripts/docker-stack.sh exec dev frontend sh
# ya dentro del container:
pnpm dev
pnpm build
pnpm typecheck
pnpm format
pnpm storybook          # UI primitives + componentes (puerto 6006)
pnpm storybook:build    # build estático para Chromatic / CI
```

## Storybook (UI primitives y showcase de componentes)

El frontend incluye **Storybook 10** para desarrollar, documentar
y probar componentes UI en aislamiento. Ver
[`docs/frontend/storybook.md`](docs/frontend/storybook.md
"docs/frontend/storybook.md") para el detalle.

```bash
cd frontend
pnpm storybook              # dev en http://localhost:6006
pnpm storybook:build        # build estático en storybook-static/
pnpm storybook:test         # smoke test del build
```

Las stories viven junto a cada componente (`*.stories.ts`).
Actuales: 13 UI primitives + 4 storefront + 4 account + 2
home — 23 stories en total.

## Diagramas de arquitectura (Archify)

La skill local **Archify** (`.agents/skills/archify/`) genera
diagramas standalone en HTML a partir de descripciones en lenguaje
natural o `Mermaid`. El último diagrama de arquitectura del
proyecto vive en:

```txt
.archify/architecture-atlascommerce-<YYYYMMDD-HHMMSS>/
└── atlascommerce-architecture.html
```

Regenerar con Archify cuando cambien módulos, instancias o
flujos cross-tenant. Ver
[`docs/architecture/architecture-diagrams.md`](docs/architecture/architecture-diagrams.md
"docs/architecture/architecture-diagrams.md") para el flujo de
trabajo.

## Puertos del entorno de desarrollo

Ver [`docs/operations/ports.md`](docs/operations/ports.md) para el detalle
completo. Resumen:

| Servicio | URL |
| --- | --- |
| Frontend | <http://localhost:5321> |
| API | <http://localhost:9000/api> |
| Postgres | `localhost:6432` |
| Redis | `localhost:7379` |
| FTP | `localhost:2121` |

Los puertos están **shifted +1000** (excepto FTP, +2100) respecto a los
defaults del servicio, para no chocar con un Postgres/Redis/Astro/php
artisan/vsftpd local en tu host.

## Módulos del Sistema

| Módulo | Descripción |
| --- | --- |
| **Auth** | Autenticación, 2FA (email OTP, TOTP, Passkeys), roles y permisos |
| **Catálogo** | Marcas, categorías, productos, variantes, atributos, imágenes |
| **Inventario** | Stock, movimientos, almacenes |
| **Clientes** | Registro, direcciones, historial |
| **Ventas** | Órdenes, facturas, historial de estados |
| **Pagos** | Métodos de pago, transacciones, cuotas |
| **Compras** | Órdenes de compra, proveedores, ítems |
| **Devoluciones** | Solicitudes de devolución, reembolsos |
| **Carrito** | Carrito de compras, lista de deseos |
| **Envíos** | Empresas de envío, seguimiento |
| **Marketing** | Cupones, promociones |
| **Impuestos** | Configuración de impuestos |
| **Soporte** | Tickets de soporte |
| **Medios** | Gestión de imágenes y archivos |
| **Configuración** | Ajustes generales por tienda |
| **Tiendas** | Multi-tienda, dominios, configuración por store |
| **Reportes** | Dashboard, métricas, exportación a Excel |
| **Sistema** | Auditoría, logs, respaldos, notificaciones |

## Documentación

- [`docs/`](docs/) — Arquitectura, diseño, operaciones
  - [`docs/architecture/overview.md`](docs/architecture/overview.md)
    — Arquitectura general
  - [`docs/architecture/multi-instance-roadmap.md`](docs/architecture/multi-instance-roadmap.md)
    — Roadmap multi-instancia
  - [`docs/architecture/architecture-diagrams.md`](docs/architecture/architecture-diagrams.md)
    — Cómo generar diagramas con Archify
  - [`docs/operations/ports.md`](docs/operations/ports.md) — Mapa de puertos
  - [`docs/operations/docker.md`](docs/operations/docker.md) — Detalle de los
    servicios Docker
  - [`docs/operations/environments.md`](docs/operations/environments.md) — Ambientes
  - [`docs/operations/caddy.md`](docs/operations/caddy.md) — Reverse proxy
  - [`docs/operations/backups.md`](docs/operations/backups.md) — Respaldos
  - [`docs/frontend/storybook.md`](docs/frontend/storybook.md) — Storybook 10
- [`backend/DOCUMENTACION.md`](backend/DOCUMENTACION.md) — Documentación técnica
  del backend
- [`backend/docs/`](backend/docs/) — Docs internos del backend

## Licencia

**Software propietario** — Copyright © 2026 Daniel Benjamin Pérez Morales (D4nitrix13). Todos
los derechos reservados. NO es código abierto; no se distribuye bajo ninguna
licencia pública (MIT, BSD, Apache, GPL u otra).

Para licenciamiento comercial, integración con terceros o cualquier uso que
no esté explícitamente autorizado, contactar al titular. Ver
[`LICENSE`](LICENSE) para los términos completos y
[`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md),
[`SECURITY.md`](SECURITY.md) y
[`SUPPORT.md`](SUPPORT.md) para los procesos formales del proyecto.

> ⚠️ Este proyecto contiene referencias históricas a "MIT" en headers de
> archivos individuales (decuando el repositorio se distribuía como código
> abierto). Esas referencias son vestigio del periodo open-source previo y
> **no otorgan ningún permiso** bajo la licencia actual. El Software en su
> conjunto se rige exclusivamente por el archivo `LICENSE` en la raíz.
