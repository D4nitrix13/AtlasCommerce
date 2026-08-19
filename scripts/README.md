# Scripts de Ecommerce

Este directorio contiene todos los scripts operativos del proyecto.
Cada script debe ser **corto, de una sola responsabilidad, y usar
rutas absolutas para los binarios** (ver "Pautas" más abajo).

## Índice

- [`docker-stack.sh`](docker-stack.sh) — entry point del orquestador
  Docker. Dispatcher delgado que delega a archivos de acción.
- [`docker/`](docker/) — submódulos del orquestador Docker.
- [`docker/_lib/`](docker/_lib/) — librería compartida entre los
  archivos de acción (colores, logging, resolve, docker_compose).
- [`docker/quality-check.sh`](docker/quality-check.sh) — verifica la
  convención de **rutas absolutas** en todos los `Dockerfile.*`,
  `docker-compose*.yml` y `entrypoint*.sh` bajo `docker/instances/`.
  Falla con exit 1 si encuentra un binario invocado bare.
- [`install-git-hooks.sh`](install-git-hooks.sh) — registra los git
  hooks del proyecto en esta máquina vía `core.hooksPath`. Setea
  `.githooks/` como directorio activo. Idempotente, soporte
  `--clean` para desinstalar.
- [`docker/help/registry.sh`](docker/help/registry.sh) — registro
  centralizado de `cmd_help_<comando>` con panel de ayuda por
  subcomando (`./docker-stack.sh volumes help` muestra este help).
  Si agregás un comando nuevo, agregá su bloque acá también.

## Estructura de `docker/`

```
docker/
├── _lib/                        # Librería compartida (prefijo _ = interna)
│   ├── colors.sh                # Constantes ANSI para logs
│   ├── logging.sh               # log_info, log_ok, log_warn, log_err, log_cmd
│   ├── resolve.sh               # Resolución de instancias / scopes / servicios
│   ├── docker_compose.sh        # Wrapper de `docker compose` + helpers de volumen
│   └── all.sh                   # Source order: colors → logging → resolve → docker_compose
├── lifecycle/                   # Ciclo de vida de containers
│   ├── up.sh                    # cmd_up
│   ├── start.sh                 # cmd_start
│   ├── stop.sh                  # cmd_stop
│   ├── restart.sh               # cmd_restart
│   ├── down.sh                  # cmd_down
│   ├── build.sh                 # cmd_build
│   └── nuke.sh                  # cmd_nuke (⚠ borra volúmenes)
├── inspection/                  # Comandos de solo lectura
│   ├── ps.sh                    # cmd_ps (vista rápida)
│   ├── info.sh                  # cmd_info (tabla detallada)
│   ├── status.sh                # cmd_status (compose ps)
│   └── logs.sh                  # cmd_logs
├── volumes/                     # Gestión de volúmenes Docker
│   ├── list.sh                  # cmd_volumes
│   └── remove.sh                # cmd_rm_volumes (⚠)
├── exec/                        # Ejecución dentro de containers
│   └── cmd.sh                   # cmd_exec
├── setup/                       # Setup y helpers de configuración
│   ├── first-install.sh         # cmd_setup
│   ├── hosts.sh                 # cmd_hosts (imprime bloque /etc/hosts)
│   └── caddy.sh                 # cmd_caddy (imprime Caddyfile)
└── help/                        # Ayuda
    └── show.sh                  # cmd_help
```

Cada carpeta tiene UN archivo `<acción>.sh` que define UNA función
`cmd_<acción>()`. El dispatcher en `docker-stack.sh` hace source
del archivo correspondiente y llama la función.

## Pautas para nuevos scripts

### 1. Tamaño y responsabilidad

- **~100 líneas máximo** por archivo. Si excede, partir en varios.
- **Una responsabilidad por archivo**. Si el script hace "start"
  y "stop" y "logs", son 3 archivos.
- Nombres descriptivos: `up.sh` mejor que `container.sh`.

### 2. Rutas absolutas para binarios (PATH hijacking)

**REGLA CRÍTICA**: usar SIEMPRE la ruta absoluta de los binarios
externos. Si alguien compromete el sistema y mete un binario falso
en `/usr/local/bin` o en el cwd, el script sigue ejecutando el
binario legítimo.

```bash
# ✅ Correcto
readonly DOCKER_BIN="/usr/bin/docker"
${DOCKER_BIN} compose ps

# ❌ Incorrecto
docker compose ps
```

Excepción: los **built-ins** del shell (`echo`, `printf`, `cd`, `pwd`,
`source`, `[[ ]]`) no son binarios externos, no les aplica PATH
hijacking y se pueden usar directos.

### 3. Imports vía `_lib/all.sh`

Los archivos de acción NO deben redefinir colores, logging o
resolución de instancias. Hacen source del lib:

```bash
# El dispatcher ya hace source de _lib/all.sh antes de cargar la
# acción, así que las funciones de logging y resolve ya están en
# scope. No repetir el source aquí.
cmd_up() {
    while IFS=$'\t' read -r inst svc; do
        log_info "Building + levantando ${inst}…"
        docker_compose "$inst" up -d --build
    done < <(resolve_targets "$@")
}
```

### 4. Path resolution centralizada

Las únicas funciones que saben dónde vive cada instancia en el FS
son `instance_compose_dir`, `instance_compose_file` y
`instance_env_file` en `_lib/resolve.sh`. Si necesitás referenciar
un path, usá estas funciones, no hardcodees paths.

### 5. Logging con colores

Usá siempre las funciones de `_lib/logging.sh`:

```bash
log_info  "mensaje informativo"
log_ok    "operación exitosa"
log_warn  "advertencia"
log_err   "error"        # va a stderr
log_cmd   "comando ejecutado"   # prefijo [cmd] dim/gris
log_bold  "texto destacado"
```

### 6. Errores con exit codes

```bash
if [[ $# -lt 3 ]]; then
    log_err "exec requiere: <instancia> <servicio> <comando...>"
    exit 1
fi
```

### 7. Comentarios al inicio del archivo

Cada script empieza con:

```bash
#!/usr/bin/env bash
# scripts/docker/<carpeta>/<archivo>.sh
# <descripción de una línea de qué hace>
# <más contexto si hace falta>
```

### 8. Verificar sintaxis antes de commitear

```bash
bash -n scripts/docker-stack.sh
bash -n scripts/docker/_lib/*.sh
bash -n scripts/docker/lifecycle/*.sh
# ...
```

### 9. Antes de commitear cambios en `docker/instances/`

Correr el quality check. Bloquea el commit si encuentra violaciones:

```bash
./scripts/docker/quality-check.sh
# Exit codes:
#   0 → OK
#   1 → una o más violaciones (commit debería abortar)
```

El proyecto tiene un **git hook** listo en `.githooks/pre-commit`
(no se activa solo al clonar). Para activarlo:

```bash
./scripts/install-git-hooks.sh           # install (set core.hooksPath)
./scripts/install-git-hooks.sh --clean   # uninstall
```

Una vez instalado, el hook detecta automáticamente cuando hay
archivos Docker staged y corre el check. Bypass consciente:
`git commit --no-verify`.

El check lee `docs/agent-skills/docker-stack-orchestration/SKILL.md`
para la lista de builtins exceptuados y la tabla de paths absolutos.
Si modificás esa tabla, el check se actualiza implícitamente.

## Convención de commits

Ver ejemplos en `git log --no-pager -n5`. Formato:

```
<tipo>(<scope>): <descripción corta imperativa>

<body detallado con:
- Qué se cambió y por qué
- Paths específicos a archivos o líneas
- Snippets before/after cuando aplique
- Lo que NO se cambió y por qué
- Cómo verificar>
```

Tipos comunes: `feat`, `fix`, `refactor`, `docs`, `chore`. Idiomas:
español para descripciones narrativas, inglés para términos técnicos.

## Añadir un comando nuevo

1. Identificar la categoría (`lifecycle/`, `inspection/`, `volumes/`,
   `exec/`, `setup/`). Si no encaja, crear nueva carpeta.
2. Crear `docker/<carpeta>/<acción>.sh` siguiendo las pautas de
   arriba.
3. Registrar el archivo en `action_file_for` de `docker-stack.sh`
   (no es un case suelto — la dispatcher es data-driven).
4. Si tiene alias corto, agregarlo en `canonicalize_cmd` de
   `docker-stack.sh` y en `is_known_cmd`.
5. Agregar `cmd_help_<comando>` en `docker/help/registry.sh` con
   el formato (USO, QUÉ HACE, EJEMPLOS) — el dispatcher lo sourcea
   cuando el usuario pide help por subcomando.
6. Agregar entrada breve en `cmd_help` (categoría correspondiente
   en `docker/help/show.sh`).
7. Probar manualmente:
   - `./scripts/docker-stack.sh <acción>` (con summary pre-cmd).
   - `./scripts/docker-stack.sh <acción> help` (per-command help).
   - `./scripts/docker-stack.sh <acción> <área> help` (help filtrado).
8. `bash -n` sobre el archivo nuevo.
9. Commit siguiendo la convención.

## Convenciones del orquestador `docker-stack.sh`

- **Resumen pre-cmd**: cada acción que usa `resolve_targets` recibe
  una línea `[<cmd>] área=<area> · n=<count> <plural>` antes de
  ejecutarse. Esto lo emite `show_target_summary` desde
  `_lib/summary.sh`, llamado por el dispatcher antes del dispatch.
- **Pre-validación**: el dispatcher llama `validate_area` antes
  del summary, para no mentir con un count cuando el área es
  desconocida. `validate_area` emite su propio error y devuelve 1.
- **Help por subcomando**: detecta la palabra `help` (o `-h`/`--help`)
  en cualquier posición después del comando. Si está, sourcea
  `help/registry.sh` y llama `cmd_help_<comando>`.
- **Aliases**: `b` → `build`, `rs` → `restart`, `ls` → `ps`,
  `psv` → `info`, `vol` → `volumes`, `rmvol` → `rm-volumes`.
  Vive en `canonicalize_cmd`.

## Área y servicios — anatomía del target

- `<área>`: `all` (default, las 6), `prod` o `dev` (las 3 de un
  scope), `production:<store>` o `development:<store>` (1 instancia).
  Bare store sin prefijo NO es válido (forzar prefijo para evitar
  ambigüedad prod vs dev).
- `<servicio>`: nombre del servicio en el compose (`backend`,
  `frontend`, `postgres`, `redis`, `ftp`, `nginx`, `mailpit`).
  Cuando se pasa, el scope efectivo es 1 target.
- `<plural>`: `instancia` (count=1) o `instancias` (count>1).