# Setup local — 4 tiendas en un solo host

Esta guía te deja **panda-estampados.com**, **kitsune.com**,
**mm-boutique.com** y **morales-store.com** (con sus subdominios
`admin.*` y `www.*`) respondiendo en `127.0.0.1` contra tres
instancias Docker independientes.

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

## 2. Script automático (recomendado)

```bash
cd Ecommerce
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
sudo cp Caddyfile /etc/caddy/Caddyfile
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

## 4. Instancias Docker

Cada instancia publica puertos distintos para que convivan en un solo
host:

| Instancia        | Backend host port | Frontend host port |
| ---------------- | ----------------- | ------------------ |
| panda-kitsune    | 8000              | 4321               |
| mm-boutique      | 8100              | 4322               |
| morales-store    | 8200              | 4323               |

Levantar las tres:

```bash
cd docker/instances/panda-kitsune
docker compose up -d
cd ../mm-boutique
docker compose up -d
cd ../morales-store
docker compose up -d
```

> Las variables de entorno (`DB_*`, `*_ADMIN_PASSWORD`, etc.) viven en
> `.env` de cada carpeta y se copian al arrancar.

## 5. Verificar routing

```bash
# API
curl -sI http://panda-estampados.com/api/storefront/theme
curl -sI http://mm-boutique.com/api/storefront/theme
curl -sI http://morales-store.com/api/storefront/theme

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
- **Puerto 80 ocupado** — `ss -tlnp | grep :80`. Si es el `caddy.service`
  viejo: `sudo systemctl stop caddy`.
- **Backend no responde** — `docker compose ps` en la carpeta de la
  instancia; revisar logs con `docker compose logs app`.
- **Quiero solo una tienda** — basta con levantar la instancia
  correspondiente y no hace falta tocar `/etc/caddy/Caddyfile` ni
  `/etc/hosts` (los hosts que no existan no se enrutan).

## Próximo paso

[`docker/instances/README.md`](../../docker/instances/README.md) — cómo
crear una nueva instancia desde cero.
