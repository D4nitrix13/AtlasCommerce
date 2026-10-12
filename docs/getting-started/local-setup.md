# Setup local — 4 tiendas en un solo host

Esta guía te deja **panda-estampados.com**, **kitsune.com**,
**mm-boutique.com** y **morales-store.com** (con sus subdominios
`admin.*` y `www.*`) respondiendo en `127.0.0.1` contra las
instancias Docker independientes de dev.

## 1. Resumen del flujo

```txt
Cliente                                  Tu host (127.0.0.1)
┌────────────┐  host:admin.kitsune.com     ┌──────────────────┐
│ Navegador  │ ─────────────────────────► │ Caddy (:80)       │
│            │                             │ reverse_proxy     │
│            │  /api/*     → backend:8000  │  → 8000 / 4321   │
│            │  /          → frontend:4321 │  → 8100 / 4322   │
│            │  * (api)   → backend:8000   │  → 8200 / 4323   │
└────────────┘                             └──────────────────┘
```

> Las 3 instancias dev comparten los mismos defaults de puerto
> host (`:9000` backend, `:5321` frontend). Para correr varias a
> la vez, shiftá los `*_PORT` en uno de los `.env` — ver
> [`operations/ports.md`](../operations/ports.md "operations/ports.md").

## 2. Script automático (recomendado)

```bash
cd AtlasCommerce
./scripts/setup-local.sh
```

El script:

1. Realiza un backup de `/etc/hosts` y `/etc/caddy/Caddyfile`.
2. Copia el Caddyfile del repo a `/etc/caddy/Caddyfile`.
3. Agrega las 12 entradas a `/etc/hosts` (idempotente).
4. Ejecuta `caddy validate`.
5. Recarga `systemd caddy` si está activo.

## 3. Pasos manuales (si no querés usar el script)

### 3.1. Hosts

Pegar al final de `/etc/hosts` (usar `sudo`):

```txt
127.0.0.1  panda-estampados.com
127.0.0.1  www.panda-estampados.com
127.0.0.1  admin.panda-estampados.com
127.0.0.1  kitsune.com
127.0.0.1  www.kitsune.com
127.0.0.1  admin.kitsune.com
127.0.0.1  mm-boutique.com
127.0.0.1  www.mm-boutique.com
127.0.0.1  admin.mm-boutique.com
127.0.0.1  morales-store.com
127.0.0.1  www.morales-store.com
127.0.0.1  admin.morales-store.com
```

### 3.2. Caddy

```bash
# El Caddyfile canónico vive en docker/caddy/Caddyfile (cubre las
# 3 instancias dev; la sección de prod se ignora si las redes de
# producción no están levantadas).
sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

## 4. Instancias Docker

Cada instancia dev vive bajo
`docker/instances/development/<store>/` y se opera vía
[`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh").
NO usar `docker compose` directo (AGENTS.md raíz).

| Store                          | Backend host port | Frontend host port |
| ------------------------------ | ----------------- | ------------------ |
| `panda-estampados-and-kitsune`  | `9000`            | `5321`             |
| `mm-boutique`                  | `9000`            | `5321`             |
| `morales-store`                | `9000`            | `5321`             |

> Si necesitás correr varias a la vez, shiftá `BACKEND_PORT`/
> `FRONTEND_PORT` en uno de los `.env` de la instancia — ver
> [`operations/ports.md`](../operations/ports.md "operations/ports.md").

Levantar las tres (de a una a la vez con los mismos defaults, o
en paralelo si shifteaste puertos):

```bash
# Panda + Kitsune (multi-tenant)
./scripts/docker-stack.sh up development panda-estampados-and-kitsune

# MM Boutique (single-tenant)
./scripts/docker-stack.sh up development mm-boutique

# Morales Store (single-tenant)
./scripts/docker-stack.sh up development morales-store

# Bajar (preserva volúmenes) cuando termines
./scripts/docker-stack.sh down development panda-estampados-and-kitsune
./scripts/docker-stack.sh down development mm-boutique
./scripts/docker-stack.sh down development morales-store
```

> Las variables de entorno (`DB_*`, `<STORE>_ADMIN_PASSWORD`, etc.)
> viven en `environment/.env` de cada carpeta. La variable
> `<STORE>` se reemplaza por `PANDA`/`MM`/`MORALES` según
> instancia (ej: `PANDA_ADMIN_PASSWORD`,
> `MM_ADMIN_PASSWORD`, `MORALES_ADMIN_PASSWORD`).

## 5. Verificar routing

```bash
# API
curl -sI http://panda-estampados.com/api/v1/storefront/theme
curl -sI http://mm-boutique.com/api/v1/storefront/theme
curl -sI http://morales-store.com/api/v1/storefront/theme

# Storefront
curl -sI http://panda-estampados.com/
curl -sI http://kitsune.com/

# Admin (rewrite interno a /es/admin/...)
curl -sI http://admin.panda-estampados.com/login
curl -sI http://admin.kitsune.com/login
curl -sI http://admin.mm-boutique.com/login
curl -sI http://admin.morales-store.com/login

# Host desconocido
curl -sI http://example.com/
```

## 6. Problemas comunes

- **Caddy no levanta** — `sudo journalctl -u caddy -n 50`.
- **Puerto 80 ocupado** — `ss -tlnp | grep :80`. Si es el
  `caddy.service` viejo: `sudo systemctl stop caddy`.
- **Backend no responde** — `scripts/docker-stack.sh status
  development <store>` y
  `scripts/docker-stack.sh logs development <store> backend -f`
  para ver logs.
- **Quiero solo una tienda** — basta con levantar la instancia
  correspondiente y no hace falta tocar `/etc/caddy/Caddyfile` ni
  `/etc/hosts` (los hosts que no existan no se enrutan).

## Próximo paso

[`docker/instances/README.md`](../../docker/instances/README.md) — cómo
crear una nueva instancia desde cero.
