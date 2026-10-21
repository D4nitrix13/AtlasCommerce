# Docker Stack — usar `scripts/docker-stack.sh` SIEMPRE

> **Regla load-bearing.** Para CUALQUIER operación con containers
> Docker del proyecto AtlasCommerce, usar **exclusivamente**
> `scripts/docker-stack.sh`. NO usar `docker compose` directamente,
> NO arrancar dev servers ad-hoc (`bun astro dev`, `npm run dev`,
> `php artisan serve`), NO matar containers manualmente.

## Por qué este script

`scripts/docker-stack.sh` es el entry point canónico del orquestador
Docker del proyecto. Detecta stage (`production` | `development`),
store (`panda-estampados-and-kitsune` | `mm-boutique` | `morales-store`
| `instrumentos-musicales-la-voz` | `all`) y servicio (`backend` |
`frontend` | `postgres` | `redis` | `ftp` | etc.) y delega a los scripts
específicos en `scripts/docker/{lifecycle,inspection,exec,db}/`.

Comandos disponibles (canonical, ver `./scripts/docker-stack.sh help`):

| Comando | Para qué sirve |
|---|---|
| `up` | Build + start containers (recrea si cambió Dockerfile/.env) |
| `start` | Levantar containers existentes SIN rebuild |
| `stop` | Detener containers preservándolos |
| `restart` | Reiniciar containers corriendo (no rebuilda) |
| `build` | Reconstruir imágenes sin arrancar |
| `down` | Detener + eliminar containers (preserva volúmenes) |
| `nuke` | down + eliminar volúmenes (BORRA BD) |
| `status` / `ps` / `info` | Inspección |
| `logs` | Tail -f de logs |
| `exec` | Ejecutar comando arbitrario dentro de un container |
| `setup` / `fresh` / `bootstrap` | Setup de instancias |
| `db-backup` / `db-restore` | Snapshot/restore de BD |
| `seed-db` | Migrate + seed |
| `sync-assets` | brand-assets/ → FTP via Artisan |

## Uso

```bash
./scripts/docker-stack.sh <cmd> [flags] <stage> <store|all> [<servicio>]
./scripts/docker-stack.sh <cmd> help          # help per-comando
./scripts/docker-stack.sh help                # help global
```

Ejemplos concretos:

```bash
# Levantar el frontend de La Voz dev después de cambios
./scripts/docker-stack.sh restart dev instrumentos-musicales-la-voz frontend

# Rebuild sin cache después de cambiar dependencias
./scripts/docker-stack.sh build --no-cache dev instrumentos-musicales-la-voz frontend

# Ver logs del backend
./scripts/docker-stack.sh logs dev instrumentos-musicales-la-voz backend

# Resetear la BD con fresh seed
./scripts/docker-stack.sh fresh dev instrumentos-musicales-la-voz

# Ejecutar artisan tinker en el backend
./scripts/docker-stack.sh exec dev instrumentos-musicales-la-voz backend /usr/local/bin/php artisan tinker
```

## Antipatrones

- ❌ `docker compose up dev instrumentos-musicales-la-voz` — bypassa el orquestador, no muestra summary, puede dejar containers en estados inconsistentes.
- ❌ `bun astro dev --host ...` o `php artisan serve` fuera del container — choca con el puerto del container, no comparte la red Docker, no resuelve `*.test` domains.
- ❌ `docker kill <container>` o `docker rm -f <container>` — el orquestador gestiona el lifecycle; matar manualmente puede dejar volúmenes huérfanos.
- ❌ Asumir que `localhost:4321` apunta al container — el frontend dev escucha en `*:4321` y se accede via `*.test:10321` (host's port).
- ❌ `npm run dev` o `pnpm dev` o `bun dev` en `/frontend` — el frontend corre dentro del container, no en el host.

## Cuándo se salta esta regla

- Investigación read-only sin tocar containers (grep, code search).
- Inspeccionar logs via `docker logs <container>` directamente si el script `logs` no aplica (e.g. ver logs de un container que el script no maneja).
- Si el usuario explícitamente pide correr un comando ad-hoc fuera del script.

## Workflow después de cambios de código

```bash
# Cambios en frontend (frontend/**)
./scripts/docker-stack.sh restart dev instrumentos-musicales-la-voz frontend

# Cambios en backend (backend/**)
./scripts/docker-stack.sh restart dev instrumentos-musicales-la-voz backend

# Cambios en DB (database/** o migrations nuevas)
./scripts/docker-stack.sh exec dev instrumentos-musicales-la-voz backend /usr/local/bin/php artisan migrate

# Cambios en Dockerfile o composer.json backend
./scripts/docker-stack.sh build --no-cache dev instrumentos-musicales-la-voz backend
./scripts/docker-stack.sh restart dev instrumentos-musicales-la-voz backend
```

## Cómo verificar que estás en el container correcto

```bash
./scripts/docker-stack.sh status dev instrumentos-musicales-la-voz
# Muestra todos los containers con puerto y comando entrypoint
```

Si necesitás ver logs en vivo, usa `./scripts/docker-stack.sh logs <stage> <store> <servicio>`. Para exec interactivo, `./scripts/docker-stack.sh exec <stage> <store> <servicio> bash`.
---

## Secuencia de reinicio tras recrear el backend

> **Esta secuencia no es opcional.** Omitir el paso 2 o el 3 produce
> HTTP 503 en toda la API con el backend funcionando perfectamente.

`fastcgi_pass backend:9000` resuelve el hostname **al arrancar** y cachea
la IP. Si recreás el backend, nginx queda apuntando a la IP del container
anterior y responde 502. Caddy ve el upstream caído y su health check
activo marca `localhost:14000` como unhealthy, así que el proxy devuelve
503 con `"no upstreams available"` — aunque el backend esté sano.

### Procedimiento

```bash
# 1. Recrear el backend
./scripts/docker-stack.sh up dev <store> backend

# 2. SIEMPRE reiniciar nginx después, para que re-resuelva `backend`
./scripts/docker-stack.sh restart dev <store> nginx

# 3. Esperar el ciclo del health check activo de Caddy (30s).
#    `host is up` debe aparecer en el journal antes de probar.
journalctl -u caddy --no-pager -n 10 | rg "14000|host is up"

# 4. Verificar antes de declarar algo roto
curl -s -o /dev/null -w "nginx  -> %{http_code}\n" http://localhost:14000/up
curl -s -o /dev/null -w "api     -> %{http_code}\n" \
  -H "Accept: application/json" \
  "http://<dominio>/api/v1/storefront/products?per_page=1"
```

Un `502` desde nginx con el backend arriba es **siempre** IP cacheada, no
un problema de la aplicación. Un `503` desde Caddy es **casi siempre** el
health check todavía en rojo.

## `up <servicio>` reconstruye más de lo que pide

`up` es *build + start*. Con el argumento de servicio **igual reconstruye
todos los servicios que tienen sección `build:`**, no solo el indicado. Si
el build de otro servicio falla, el que querías recrear tampoco se recrea.

### Recrear un solo servicio sin rebuildear imágenes

Cuando el build está roto en otro servicio, o cuando ya construiste la
imagen por separado y solo querés recrear el container, usar el flag
`--no-build` del orquestador (en lugar de invocar `docker compose`
directo, lo cual está prohibido arriba):

```bash
./scripts/docker-stack.sh up --no-build dev instrumentos-musicales-la-voz frontend
# Internamente: docker compose up -d --no-build --force-recreate --remove-orphans frontend
```

Internamente `docker_compose` agrega `--force-recreate` al `--no-build`
porque, sin build, compose no detecta cambios y NO recrearía el container
"viejo" con la imagen ya construida. Con `--force-recreate`, el container
se recrea aunque su config no haya cambiado — que es exactamente lo que
querés cuando pediste explícitamente `--no-build`.

**NO usar `--no-build` después de un cambio en el Dockerfile**: el
container quedaría con la imagen anterior. Para cambios de Dockerfile,
usar `up` (sin flag) o `build --no-cache` + `start`.

El flag `--no-build` también es útil como alternativa al clásico
`docker compose ... up -d --no-build --force-recreate` directo, sin
necesidad de caer al bypass del orquestador (ver
[Antipatrones](#antipatrones)).

## Caché del dev server de Astro

Los volúmenes `*_astro_cache` y `*_vite_cache` sirven HTML y config viejos
aunque el código fuente ya esté actualizado. Si un cambio en `frontend/src`,
en los paquetes de tema o en variables de entorno no se refleja:

```bash
docker volume rm <store>_dev_astro_cache <store>_dev_vite_cache
docker compose --env-file environment/.env \
  up -d --no-build --force-recreate frontend
```

## Assets en desarrollo

El `AssetController` sirve desde el disco configurado en
`ASSETS_DISK`. En desarrollo va `local`, porque el seeder puebla ese disco
y el FTP queda vacío. Ver `backend/config/assets.php` y el `.env.example`
de la instancia. Si las imágenes dan 404 y el backend está sano, lo
primero que hay que mirar es ese valor.
