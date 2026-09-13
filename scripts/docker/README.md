# scripts/docker/ — orquestador de instancias

Este directorio contiene todos los archivos de accion del orquestador
`docker-stack.sh`. Cada uno exporta UNA funcion `cmd_<comando>()` que el
dispatcher invoca.

Para entender CUAL es el comando disponible y QUE hace, correr:

```bash
./scripts/docker-stack.sh help                    # help global (lista todos)
./scripts/docker-stack.sh <cmd> help              # help del comando especifico
./scripts/docker-stack.sh <cmd>                   # resumen pre-cmd antes de ejecutar
```

El help de cada comando esta en `help/registry.sh` (per-comando) +
`help/show.sh` (help global). El codigo real de cada accion vive en el
archivo `<carpeta>/<comando>.sh`.

---

## Estructura

```txt
docker/
├── _lib/                        # Libreria compartida (no son comandos)
│   ├── colors.sh                # Constantes ANSI
│   ├── logging.sh               # log_info, log_ok, log_warn, log_err
│   ├── resolve.sh               # Resolucion stage/store/svc
│   ├── docker_compose.sh        # Wrapper de docker compose
│   ├── summary.sh               # show_target_summary pre-cmd
│   └── all.sh                   # Source order (no agregar como accion)
├── lifecycle/                   # Ciclo de vida de containers
├── inspection/                  # Comandos de solo lectura
├── volumes/                     # Gestion de volumenes Docker
├── exec/                        # Ejecucion dentro de containers
├── setup/                       # Setup + bootstrap + hosts
├── db/                          # Backup / restore de BD
├── help/                        # Help global + per-comando
└── quality-check.sh             # Verifica paths absolutos en Dockerfiles
```

---

## Indice de comandos (todos)

### Lifecycle — `lifecycle/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `up.sh` | `cmd_up` | build + start containers. Detecta cambios en env_file y recrea. |
| `start.sh` | `cmd_start` | Levantar containers SIN rebuild (usa state guardado). |
| `stop.sh` | `cmd_stop` | Detener containers preservando volumenes. |
| `restart.sh` | `cmd_restart` | Reinicia procesos. NO recarga .env (usar `up` para eso). |
| `build.sh` | `cmd_build` | Rebuild imagenes. `--no-cache` para forzar full rebuild. |
| `down.sh` | `cmd_down` | Detener + eliminar containers preservando volumenes. |
| `nuke.sh` | `cmd_nuke` | down + eliminar volumenes (BORRA DATOS). Requiere confirm. |

### Inspection — `inspection/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `status.sh` | `cmd_status` | `docker compose ps` filtrado por stage/store. |
| `ps.sh` | `cmd_ps` | Lista TODOS los containers del host en una sola tabla. |
| `info.sh` | `cmd_info` | Tabla detallada: instancia · servicio · estado · puertos. |
| `logs.sh` | `cmd_logs` | Tail de logs. `Ctrl-C` para salir. Servicio REQUERIDO. |

### Volumes — `volumes/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `list.sh` | `cmd_volumes` | Lista volumenes Docker declarados. |
| `remove.sh` | `cmd_rm_volumes` | Elimina volumenes (BORRA DATOS). `-y` para skip confirm. |

### Exec — `exec/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `cmd.sh` | `cmd_exec` | Ejecuta comando dentro de un container. Servicio + comando REQUERIDOS. |

### Setup — `setup/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `first-install.sh` | `cmd_setup` | Primera instalacion: copia .env, levanta stacks. `store=all` aplica a todas. |
| `bootstrap.sh` | `cmd_bootstrap` | Idempotente. Ejecuta 8 pasos post-deploy: pg_isready, db:restore, storage:link, key:generate, cache:clear, migrate, tenants:bootstrap, db:seed. |
| `fresh-instance.sh` | `cmd_fresh` | Fresh start de 1 instancia: down -v + up -d --build + pre-initdb + migrate + seed + tenants:bootstrap. `store=all` NO permitido. |
| `hosts.sh` | `cmd_hosts` | Imprime el bloque para `/etc/hosts` con las IPs internas. |
| `caddy.sh` | `cmd_caddy` | Imprime el bloque Caddyfile para los dominios. |

### DB — `db/` (nuevo)

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `backup.sh` | `cmd_db_backup` | Exporta BD via `pg_dump` + gzip a `db-backups/`. `--no-compress`, `--schema-only`, `--output`. |
| `restore.sh` | `cmd_db_restore` | Carga dump .sql/.sql.gz a la BD. Pre-backup automatico. `--force`, `--truncate`, `--skip-migrate`. |

### Help — `help/`

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `show.sh` | `cmd_help` | Help global (lista todos los comandos). |
| `registry.sh` | `cmd_help_<comando>` por comando | Help detallado per-comando. Si agregas un comando nuevo, anade el `cmd_help_X` aca. |

### Quality — root

| Archivo | Funcion | Que hace |
| --- | --- | --- |
| `quality-check.sh` | (standalone) | Verifica la convencion de paths absolutos en `docker/instances/`. Lo corre el git hook pre-commit. |

---

## Convenciones del orquestador

### Rutas absolutas en Dockerfiles

REGLA CRITICA: usar SIEMPRE rutas absolutas para binarios en
`Dockerfile.*`, `docker-compose*.yml` y `entrypoint*.sh` bajo
`docker/instances/`. Mitiga PATH hijacking.

El `quality-check.sh` verifica esto automaticamente. Exit 1 si encuentra
un binario bare. El git hook pre-commit corre este check.

### Sources en `_lib/`

El dispatcher hace source de `_lib/all.sh` una sola vez antes de cada
accion. Eso importa: `colors → logging → resolve → summary →
docker_compose`. NO duplicar imports en cada `cmd_*()`.

### Container name vs instance name

| Concepto | Formato | Ejemplo |
| --- | --- | --- |
| Instance (resolve_targets) | `<stage>:<store>` | `development:panda-estampados-and-kitsune` |
| Container (docker ps) | `<store>-<stage_alias>-<svc>` | `panda-estampados-and-kitsune-dev-postgres` |
| Stage alias | `dev` o `prod` | dev (no `development`) |
| Postgres user/db | derivado del slug (`-` → `_`) | `panda_estampados` |

### Paths en el output

Para que el proyecto sea portable (descargable en cualquier maquina),
los scripts muestran paths RELATIVOS al `ROOT_DIR` del repo en vez
de paths absolutos del filesystem del host. Ej: `./db-backups/...` en
vez de `/home/user/proj/db-backups/...`.

Si necesitas el path absoluto para pasarlo a otro comando, usa
`$(realpath ./db-backups/...)` o `readlink -f`.

---

## Workflow tipico

```bash
# 1. Primera vez
./scripts/docker-stack.sh setup dev panda-estampados-and-kitsune

# 2. Bootstrap post-deploy (migrations, key, caches)
./scripts/docker-stack.sh bootstrap dev panda-estampados-and-kitsune

# 3. Si queres datos reales en vez de seeders fake
./scripts/docker-stack.sh bootstrap dev panda-estampados-and-kitsune \
    --with-backup dev-panda-estampados-and-kitsune-2026-08-26-1430.sql.gz

# 4. Inspeccionar
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune backend

# 5. Ejecutar comando dentro del backend
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend \
    /usr/local/bin/php artisan tinker

# 6. Backup despues de tener data util
./scripts/docker-stack.sh db-backup dev panda-estampados-and-kitsune

# 7. Destruir todo (cUIDADO, BORRA DATOS)
./scripts/docker-stack.sh nuke dev all -y
./scripts/docker-stack.sh rm-volumes -y dev all

# 8. Setup desde cero
./scripts/docker-stack.sh setup dev all
```

---

## Troubleshooting

### "Container not found" / "role does not exist"

El container y el user/DB Postgres siguen un patron. Si ves errores
de "role postgres does not exist", probablemente estas usando
`${DB_USERNAME:-postgres}` como fallback. Solucion: usar el slug
del store derivado (`${store//-/_}`), como hacen `db-backup`,
`db-restore` y `bootstrap`.

### Output del comando no se puede redirigir a archivo

El wrapper `docker_compose()` loguea el comando via `log_cmd_block`
a stdout ANTES del output del comando. Si necesitas redirigir
stdout limpio (ej: `pg_dump` a archivo), usa `docker exec` directo
NO el wrapper. Ver `db/backup.sh` para el patron correcto.

### El comando `setup` no corre migraciones

`setup` solo levanta containers. Para correr migraciones, key:generate,
caches, etc. corre `bootstrap` despues:

```bash
./scripts/docker-stack.sh setup dev panda-estampados-and-kitsune
./scripts/docker-stack.sh bootstrap dev panda-estampados-and-kitsune
```

O atomicamente:

```bash
./scripts/docker-stack.sh setup dev panda-estampados-and-kitsune && \
./scripts/docker-stack.sh bootstrap dev panda-estampados-and-kitsune
```

### db-backup genera archivo vacio

Posibles causas:

1. La BD esta vacia (sin tablas) — pg_dump genera solo comentarios
2. El rol/user no existe — usa el slug derivado del store
3. El container no esta corriendo — corre `docker compose ps` primero

### `git commit` falla con "violacion de paths absolutos"

El git hook pre-commit corre `quality-check.sh` que verifica paths
absolutos en `docker/instances/`. Si intencionalmente necesitas
un path no absoluto, usa `git commit --no-verify` con justificacion
en el mensaje.

---

## Como anadir un comando nuevo

1. Identificar la categoria (`lifecycle/`, `inspection/`, `volumes/`,
   `exec/`, `setup/`, `db/`). Si no encaja, crear nueva carpeta.
2. Crear `docker/<carpeta>/<accion>.sh` siguiendo las pautas de
   `scripts/README.md`.
3. Registrar el archivo en `action_file_for` de `docker-stack.sh`.
4. Si tiene alias corto, agregarlo en `canonicalize_cmd`.
5. Anadir en `is_known_cmd`.
6. Si requiere flags especiales, agregar caso en el parser de flags
   y en `KNOWN_FLAGS`.
7. Si tiene dispatch especial (ej: `db-restore` con archivo + flags),
   agregar caso en el dispatch al final del dispatcher.
8. Anadir `cmd_help_<comando>` en `docker/help/registry.sh`.
9. Anadir entrada breve en `cmd_help` (categoria correspondiente en
   `docker/help/show.sh`).
10. Probar manualmente:
    - `./scripts/docker-stack.sh <accion>` (con summary pre-cmd).
    - `./scripts/docker-stack.sh <accion> help` (per-command help).
11. `bash -n scripts/docker/<carpeta>/<accion>.sh` antes de commitear.
12. Commit siguiendo la convención de `scripts/README.md`.
