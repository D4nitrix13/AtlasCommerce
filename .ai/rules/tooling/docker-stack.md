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