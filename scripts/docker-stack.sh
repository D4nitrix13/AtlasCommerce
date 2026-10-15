#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker-stack.sh — entry point del orquestador Docker
# ------------------------------------------------------------
# Dispatcher delgado con detección de "help" por subcomando y
# resumen de stage + store + servicio antes de cada ejecución.
#
# Comandos disponibles (canonical):
#   Lifecycle   : up start stop restart build down nuke
#   Inspección  : status ps info logs
#   Volumes     : volumes rm-volumes
#   Exec        : exec
#   Setup       : setup fresh bootstrap hosts caddy
#   DB          : db-backup db-restore
#   Help        : help
#
# Cada acción vive en su propia carpeta:
#   scripts/docker/up/up.sh          → cmd_up
#   scripts/docker/volumes/list.sh   → cmd_volumes
#   scripts/docker/help/show.sh      → cmd_help
#   etc.
#
# Help per-comando vive en:
#   scripts/docker/help/registry.sh  → cmd_help_<comando>
#
# Convenciones aplicadas (ver scripts/README.md):
#   - Rutas absolutas para binarios (/usr/bin/docker, /usr/bin/id)
#     para evitar PATH hijacking.
#   - Archivos cortos, una responsabilidad cada uno.
#   - Ningún script excede ~100 líneas salvo help.
#   - Dispatcher delgado, lógica en lib/ y en cada acción.
#
# Uso:
#   ./scripts/docker-stack.sh <cmd> [flags] <stage> <store|all> [<servicio>]
#   ./scripts/docker-stack.sh <cmd> help                  # help del comando
#   ./scripts/docker-stack.sh help                        # help global
#
# Stages canónicos:
#   production | prod    → 3 instancias de producción
#   development | dev    → 3 instancias de development
#
# Stores:
#   panda-estampados-and-kitsune | mm-boutique | morales-store   → 1 instancia
#   instrumentos-musicales-la-voz                        → 1 instancia
#   all                                            → todas las del stage
#
# Requiere: docker compose v2 (>= 5.x), bash 4+.
# ------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && /usr/bin/pwd)"
DOCKER_DIR="${SCRIPT_DIR}/docker"
LIB_DIR="${DOCKER_DIR}/_lib"

# Cargar lib compartida UNA sola vez. El orden importa (colors →
# logging → resolve → summary → docker_compose); está documentado
# en all.sh.
# shellcheck source=scripts/docker/_lib/all.sh
source "${LIB_DIR}/all.sh"

# ─────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────

# Canonicalizar alias a nombre canónico del comando.
#   vol → volumes, b → build, etc.
# Devuelve el mismo string si no es alias.
canonicalize_cmd() {
    case "$1" in
        b)    printf 'build\n' ;;
        rs)   printf 'restart\n' ;;
        ls)   printf 'ps\n' ;;
        psv)  printf 'info\n' ;;
        vol)  printf 'volumes\n' ;;
        rmvol) printf 'rm-volumes\n' ;;
        *)    printf '%s\n' "$1" ;;
    esac
}

# Resolver el archivo .sh que contiene cmd_<comando>.
# Devuelve path absoluto si lo encuentra, vacío si no.
action_file_for() {
    local c="$1"
    case "$c" in
        up|start|stop|restart|build|down|nuke)
            printf '%s\n' "${DOCKER_DIR}/lifecycle/${c}.sh" ;;
        status|ps|info|logs)
            printf '%s\n' "${DOCKER_DIR}/inspection/${c}.sh" ;;
        volumes)
            printf '%s\n' "${DOCKER_DIR}/volumes/list.sh" ;;
        rm-volumes)
            printf '%s\n' "${DOCKER_DIR}/volumes/remove.sh" ;;
        exec)        printf '%s\n' "${DOCKER_DIR}/exec/cmd.sh" ;;
        setup)       printf '%s\n' "${DOCKER_DIR}/setup/first-install.sh" ;;
        bootstrap)   printf '%s\n' "${DOCKER_DIR}/setup/bootstrap.sh" ;;
        fresh)       printf '%s\n' "${DOCKER_DIR}/setup/fresh-instance.sh" ;;
        hosts)       printf '%s\n' "${DOCKER_DIR}/setup/hosts.sh" ;;
        caddy)       printf '%s\n' "${DOCKER_DIR}/setup/caddy.sh" ;;
        db-backup)   printf '%s\n' "${DOCKER_DIR}/db/backup.sh" ;;
        db-restore)  printf '%s\n' "${DOCKER_DIR}/db/restore.sh" ;;
        seed-db)     printf '%s\n' "${DOCKER_DIR}/db/seed.sh" ;;
        sync-assets) printf '%s\n' "${DOCKER_DIR}/assets/sync.sh" ;;
        help)        printf '%s\n' "${DOCKER_DIR}/help/show.sh" ;;
        *)           printf '\n' ;;
    esac
}

# True si el string es un comando conocido (canonical o alias).
is_known_cmd() {
    case "$1" in
        up|start|stop|restart|build|down|nuke|\
        status|ps|info|logs|\
        volumes|rm-volumes|\
        exec|setup|bootstrap|fresh|hosts|caddy|\
        db-backup|db-restore|\
        seed-db|sync-assets|\
        help|\
        b|rs|ls|psv|vol|rmvol) return 0 ;;
        *) return 1 ;;
    esac
}

# True si la palabra "help" (o -h/--help) aparece en args.
args_contain_help() {
    local a
    for a in "$@"; do
        case "$a" in
            help|-h|--help) return 0 ;;
        esac
    done
    return 1
}

# Imprime el bloque de USO cuando faltan argumentos posicionales.
# Usado por casi todos los comandos con args requeridos.
#
# Objetivo: mensaje claro y sin ruido (pre-2026-10-15 hacia prefijar
# cada linea con `[error]` via log_err, lo que ensuciaba el output).
# Ahora:
#   1. Una linea con el comando + descripcion corta de QUE hace.
#   2. Un bloque "Te falta:" con los args que faltan.
#   3. 1-2 ejemplos concretos copiables.
#   4. Link a `./scripts/docker-stack.sh help <cmd>` para el detalle.
#
# El caller (dispatcher) ya computó que el comando es conocido, asi
# que $cmd_canonical es uno de los listados en `is_known_cmd`.
_print_positional_usage() {
    local cmd_canonical="$1"
    local script_path="$2"

    # Descripcion corta de cada comando. Si no esta en este switch,
    # cae al default que solo dice "uso" generico.
    local desc="" required_args="" examples="" more_help=""
    case "$cmd_canonical" in
        up)             desc="Build + start containers (recrea si cambiaron Dockerfile/.env)"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="Levantar TODOS los servicios de Panda dev:
  ${C_BRIGHT_CYAN}${script_path} up dev panda-estampados-and-kitsune all${C_RESET}

Solo el backend de Panda dev:
  ${C_BRIGHT_CYAN}${script_path} up dev panda-estampados-and-kitsune backend${C_RESET}"
                        ;;
        start)          desc="Levantar containers existentes SIN rebuild"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} start dev panda-estampados-and-kitsune backend${C_RESET}"
                        ;;
        stop)           desc="Detener containers preservandolos (no elimina)"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} stop dev panda-estampados-and-kitsune all${C_RESET}"
                        ;;
        restart)        desc="Reiniciar containers corriendo (no rebuilda)"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} restart dev panda-estampados-and-kitsune backend${C_RESET}"
                        ;;
        down)           desc="Detener + eliminar containers (preserva volumenes)"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} down dev panda-estampados-and-kitsune all${C_RESET}"
                        ;;
        build)          desc="Reconstruir imagenes Docker sin arrancar"
                        required_args="[--no-cache] <stage> <store|all> [<servicio>|all]"
                        examples="Con cache:    ${C_BRIGHT_CYAN}${script_path} build dev all${C_RESET}
Sin cache:    ${C_BRIGHT_CYAN}${script_path} build --no-cache production all${C_RESET}
Por servicio: ${C_BRIGHT_CYAN}${script_path} build dev panda-estampados-and-kitsune backend${C_RESET}"
                        ;;
        ps)             desc="Lista rapida de TODOS los containers (sin args)"
                        required_args="(sin args)"
                        examples="${C_BRIGHT_CYAN}${script_path} ps${C_RESET}"
                        ;;
        status)         desc="Estado por servicio via docker compose ps"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} status dev panda-estampados-and-kitsune${C_RESET}"
                        ;;
        info)           desc="Tabla detallada: instancia + servicio + estado + puertos + comando"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} info dev panda-estampados-and-kitsune${C_RESET}"
                        ;;
        logs)           desc="Tail -f de logs (servicio REQUERIDO)"
                        required_args="<stage> <store|all> <servicio>"
                        examples="${C_BRIGHT_CYAN}${script_path} logs dev panda-estampados-and-kitsune backend${C_RESET}"
                        ;;
        volumes)        desc="Volumenes Docker agrupados con tamaño real"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} volumes dev all${C_RESET}"
                        ;;
        exec)           desc="Ejecutar comando arbitrario dentro de un container"
                        required_args="<stage> <store> <servicio> <comando...>"
                        examples="bash en backend:        ${C_BRIGHT_CYAN}${script_path} exec dev panda-estampados-and-kitsune backend bash${C_RESET}
artisan tinker:          ${C_BRIGHT_CYAN}${script_path} exec dev panda-estampados-and-kitsune backend /usr/local/bin/php artisan tinker${C_RESET}"
                        ;;
        setup)          desc="Primera instalacion completa (.env + up + migrate + cache + tenants)"
                        required_args="<stage> <store|all>"
                        examples="${C_BRIGHT_CYAN}${script_path} setup dev panda-estampados-and-kitsune${C_RESET}
  ${C_BRIGHT_CYAN}${script_path} setup dev all${C_RESET}"
                        ;;
        bootstrap)      desc="Regenerar APP_KEY del backend si falta (idempotente)"
                        required_args="<stage> <store>"
                        examples="${C_BRIGHT_CYAN}${script_path} bootstrap dev panda-estampados-and-kitsune${C_RESET}"
                        ;;
        db-backup)      desc="Exportar BD a db-backups/<stage>-<store>-<ts>.sql.gz"
                        required_args="<stage> <store> [--schema-only] [--output <path>]"
                        examples="${C_BRIGHT_CYAN}${script_path} db-backup dev panda-estampados-and-kitsune${C_RESET}
Schema solo: ${C_BRIGHT_CYAN}${script_path} db-backup production mm-boutique --schema-only${C_RESET}"
                        ;;
        db-restore)     desc="Cargar dump en BD (DESTRUCTIVO: pisa BD)"
                        required_args="<stage> <store> <archivo> [--force] [--no-pre-backup] [--skip-migrate] [--truncate]"
                        examples="${C_BRIGHT_CYAN}${script_path} db-restore dev panda-estampados-and-kitsune \\
  dev-panda-estampados-and-kitsune-2026-08-26-1430.sql.gz${C_RESET}"
                        ;;
        seed-db)        desc="Migrate + (opcional --seed y/o --sql <archivo>)"
                        required_args="<stage> <store> [--seed] [--sql <archivo>]"
                        examples="${C_BRIGHT_CYAN}${script_path} seed-db dev panda-estampados-and-kitsune${C_RESET}
Con seeders: ${C_BRIGHT_CYAN}${script_path} seed-db dev panda-estampados-and-kitsune --seed${C_RESET}"
                        ;;
        sync-assets)    desc="brand-assets/ → FTP via artisan"
                        required_args="<stage> <store|all> [--dry-run] [--force]"
                        examples="${C_BRIGHT_CYAN}${script_path} sync-assets dev panda-estampados-and-kitsune${C_RESET}
Preview:    ${C_BRIGHT_CYAN}${script_path} sync-assets dev all --dry-run${C_RESET}"
                        ;;
        nuke)           desc="down + eliminar volumenes (BORRA BD)"
                        required_args="<stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} nuke dev panda-estampados-and-kitsune${C_RESET}
Pedira confirmacion escribiendo 'yes'.

Para saltar la confirmacion (CUIDADO en prod):"
                        ;;
        rm-volumes)     desc="Eliminar volumenes sin tocar containers [-y para skip]"
                        required_args="[-y] <stage> <store|all> [<servicio>|all]"
                        examples="${C_BRIGHT_CYAN}${script_path} rm-volumes -y dev panda-estampados-and-kitsune${C_RESET}"
                        ;;
        fresh)          desc="down -v + rebuild + pre-initdb + migrate + seed + tenants"
                        required_args="<stage> <store>"
                        examples="${C_BRIGHT_CYAN}${script_path} fresh dev panda-estampados-and-kitsune${C_RESET}
${C_BRIGHT_CYAN}${script_path} fresh production mm-boutique${C_RESET}"
                        ;;
        hosts)          desc="Imprime el bloque a pegar en /etc/hosts (sin args)"
                        required_args="(sin args)"
                        examples="${C_BRIGHT_CYAN}${script_path} hosts | sudo tee -a /etc/hosts${C_RESET}"
                        ;;
        caddy)          desc="Imprime el Caddyfile para enrutar las 6 instancias"
                        required_args="(sin args)"
                        examples="${C_BRIGHT_CYAN}${script_path} caddy | sudo tee /etc/caddy/Caddyfile && \\
  sudo systemctl restart caddy${C_RESET}"
                        ;;
        help)           desc="Help global o per-comando (uso: ${script_path} help [cmd])"
                        required_args="(sin args, o [comando])"
                        examples="${C_BRIGHT_CYAN}${script_path} help${C_RESET}
${C_BRIGHT_CYAN}${script_path} help up${C_RESET}"
                        ;;
        *)              desc="Comando del orquestador docker-stack"
                        required_args="<stage> <store|all> [<servicio>]"
                        examples="${C_BRIGHT_CYAN}${script_path} ${cmd_canonical} dev panda-estampados-and-kitsune${C_RESET}
${C_BRIGHT_CYAN}${script_path} ${cmd_canonical} dev all${C_RESET}"
                        ;;
    esac

    # Render. Sin `[error]` prefijo — esto NO es un error fatal, es un
    # usage para el usuario. Usamos log_info / printf directo.
    printf "\n"
    printf "${C_BRIGHT_MAGENTA}${cmd_canonical}${C_RESET}  ${C_DIM}—${C_RESET} %s\n" "$desc"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}${script_path}${C_RESET} ${C_BRIGHT_MAGENTA}${cmd_canonical}${C_RESET} ${C_BRIGHT_YELLOW}%s${C_RESET}\n" "$required_args"
    if [[ -n "$examples" ]]; then
        printf "\n"
        printf "${C_BOLD}Ejemplos${C_RESET}\n"
        # Cada linea del bloque ejemplos con indent de 2 espacios.
        while IFS= read -r line; do
            printf "  %b\n" "$line"
        done <<< "$examples"
    fi
    printf "\n"
    printf "${C_BOLD}Etapas:${C_RESET} ${C_BRIGHT_YELLOW}production${C_RESET} | ${C_BRIGHT_YELLOW}development${C_RESET}\n"
    printf "${C_BOLD}Tiendas:${C_RESET} ${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET} | ${C_BRIGHT_CYAN}mm-boutique${C_RESET} | ${C_BRIGHT_CYAN}morales-store${C_RESET} | ${C_BRIGHT_CYAN}instrumentos-musicales-la-voz${C_RESET} | ${C_BRIGHT_CYAN}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Detalle:${C_RESET} ${C_BRIGHT_CYAN}${script_path} help ${cmd_canonical}${C_RESET}\n"
    printf "\n"
}

# ─────────────────────────────────────────────────────────────
# Dispatch
# ─────────────────────────────────────────────────────────────

# Sin args → global help.
if [[ $# -eq 0 ]]; then
    # shellcheck source=scripts/docker/help/show.sh
    source "${DOCKER_DIR}/help/show.sh"
    cmd_help
    exit 0
fi

cmd_raw="$1"
cmd_canonical="$(canonicalize_cmd "$cmd_raw")"

# Primer arg = help/-h/--help → help global o per-comando.
case "$cmd_raw" in
    help|-h|--help)
        # shellcheck source=scripts/docker/help/show.sh
        source "${DOCKER_DIR}/help/show.sh"
        if [[ $# -ge 2 ]]; then
            # `help <comando>` → help específico del comando.
            shift  # descartar "help"
            cmd_help "$@"
        else
            # Sin args → índice compacto de todos los comandos.
            cmd_help
        fi
        exit 0
        ;;
esac

# Comando desconocido → error + global help.
if ! is_known_cmd "$cmd_raw"; then
    log_err "Comando desconocido: $cmd_raw"
    echo
    # shellcheck source=scripts/docker/help/show.sh
    source "${DOCKER_DIR}/help/show.sh"
    cmd_help
    exit 1
fi

# Si cualquier arg después del comando es "help" → per-command help.
# Toda la lógica de help per-comando vive en scripts/docker/help/show.sh
# (función `_cmd_specific_help` despachada desde `cmd_help <comando>`).
# Acá solo detectamos el flag y delegamos.
if args_contain_help "${@:2}"; then
    # shellcheck source=scripts/docker/help/show.sh
    source "${DOCKER_DIR}/help/show.sh"
    cmd_help "$cmd_canonical"
    exit 0
fi

# Dispatch: comandos que NO usan stage/store como posicionales.
# Pasan todos sus args tal cual a cmd_<cmd>.
case "$cmd_canonical" in
    help|hosts|caddy)
        action_path="$(action_file_for "$cmd_canonical")"
        if [[ -z "$action_path" || ! -f "$action_path" ]]; then
            log_err "Acción no encontrada para el comando '$cmd_canonical'"
            exit 1
        fi
        # shellcheck source=/dev/null
        source "$action_path"
        cmd_fn="cmd_${cmd_canonical//-/_}"
        if ! declare -F "$cmd_fn" > /dev/null; then
            log_err "Comando '$cmd_canonical' no exporta la función $cmd_fn"
            exit 1
        fi
        shift  # descarta el nombre del comando
        "$cmd_fn" "$@"
        exit 0
        ;;
esac

# ─────────────────────────────────────────────────────────────
# Dispatch: comandos con posicionales <stage> <store> [<servicio>]
# ─────────────────────────────────────────────────────────────

# Flags conocidos por comando. Los filtramos de los args
# posicionales para que no se cuenten como stage/store/service.
# También los exponemos via env vars para que cmd_* los lea sin
# cambiar la firma (escala a más flags sin tocar funciones).
#
# Patrón de flags:
#   build      → no-cache      (en cualquier posición)
#   rm-volumes → -y            (skip confirmation)
#   setup      → --install-caddy / -install-caddy
#                              (copia Caddyfile a /etc/caddy/ y reinicia)
HAS_NO_CACHE=0
RM_VOLUMES_SKIP_CONFIRM=0
SETUP_INSTALL_CADDY=0
KNOWN_FLAGS=()
case "$cmd_canonical" in
    build)
        KNOWN_FLAGS=("no-cache")
        ;;
    rm-volumes)
        KNOWN_FLAGS=("-y")
        ;;
    sync-assets)
        KNOWN_FLAGS=("--dry-run" "--force")
        ;;
    setup)
        KNOWN_FLAGS=("--install-caddy" "-install-caddy")
        ;;
    *)
        KNOWN_FLAGS=()
        ;;
esac

# Flags booleanos detectados para pasarlos a cmd_*. Seteamos la env
# var aca (en lugar de pasarlos como args posicionales) para que la
# firma cmd_*(stage, store, ...) sea estable y los flags se acumulen
# en una sola variable por comando.
SYNC_ASSETS_DRY_RUN=0
SYNC_ASSETS_FORCE=0

# Filtrar flags → POSITIONAL (en orden) + activar flags detectados.
POSITIONAL=()
for arg in "$@"; do
    # Saltar el primer arg (que es el nombre del comando: $cmd_raw).
    # El loop arranca en $@ completo; descartamos arg1 acá.
    [[ "$arg" == "$cmd_raw" ]] && continue

    is_flag=0
    for flag in "${KNOWN_FLAGS[@]}"; do
        if [[ "$arg" == "$flag" ]]; then
            is_flag=1
            case "$flag" in
                no-cache)         HAS_NO_CACHE=1 ;;
                -y)               RM_VOLUMES_SKIP_CONFIRM=1 ;;
                --dry-run)        SYNC_ASSETS_DRY_RUN=1 ;;
                --force)          SYNC_ASSETS_FORCE=1 ;;
                --install-caddy)  SETUP_INSTALL_CADDY=1 ;;
                -install-caddy)   SETUP_INSTALL_CADDY=1 ;;
            esac
            break
        fi
    done
    [[ $is_flag -eq 0 ]] && POSITIONAL+=("$arg")
done

# Mínimo de posicionales por comando.
case "$cmd_canonical" in
    exec)         MIN_POS=4 ;;  # stage, store, servicio, cmd... (>=1 cmd token)
    logs)         MIN_POS=3 ;;  # stage, store, servicio (servicio REQUERIDO)
    db-restore)   MIN_POS=3 ;;  # stage, store, archivo
    *)            MIN_POS=2 ;;  # stage, store
esac

if [[ ${#POSITIONAL[@]} -lt $MIN_POS ]]; then
    _print_positional_usage "$cmd_canonical" "$0"
    exit 1
fi

STAGE="${POSITIONAL[0]}"
STORE="${POSITIONAL[1]}"
# Para db-restore, POSITIONAL[2] es el archivo (no servicio).
# Para el resto, POSITIONAL[2] es servicio (puede estar vacio).
if [[ "$cmd_canonical" == "db-restore" ]]; then
    SERVICE=""
else
    SERVICE="${POSITIONAL[2]:-}"
fi
# Args extra después de servicio/archivo (usados por exec y db-restore).
EXTRA=("${POSITIONAL[@]:3}")

# Validar stage + store. Errores claros por separado.
if ! validate_stage_store "$STAGE" "$STORE"; then
    echo ""
    _print_positional_usage "$cmd_canonical" "$0"
    exit 1
fi

# Normalizar stage para uso interno (alias → canónico).
case "$STAGE" in
    prod) STAGE="production" ;;
    dev)  STAGE="development" ;;
esac

# Para `logs`, el servicio es OBLIGATORIO (el dispatcher ya
# exige MIN_POS=3, pero por las dudas revalidamos).
if [[ "$cmd_canonical" == "logs" && -z "$SERVICE" ]]; then
    log_err "logs requiere <servicio>."
    log_err "Uso: ${C_BRIGHT_CYAN}$0 logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<store|all>${C_RESET} ${C_GREEN}<servicio>${C_RESET}"
    log_err "  ${C_BRIGHT_CYAN}$0 logs dev panda-estampados-and-kitsune backend${C_RESET}"
    exit 1
fi

# Comandos que operan sobre servicios: si el usuario pasa una tienda
# ESPECIFICA (no "all"), DEBE especificar tambien el servicio. Asi
# se evita el typo silencioso de correr `up dev panda-estampados-and-kitsune`
# (sin servicio) que arrancaba TODOS los servicios (postgres, redis, ftp,
# backend, frontend, nginx, mailpit, pgadmin, redis-insight, filebrowser).
#
# "all" tiene 2 significados validos:
#   - Como STORE: TODAS las instancias del stage (`up dev all`)
#   - Como SERVICE: TODOS los servicios de UNA instancia (`up dev <store> all`)
# Cuando SERVICE es "all" y STORE es especifico, lo traducimos a string
# vacio para que docker compose arranque todos los servicios. Asi:
#   `up dev all`                  → todas las instancias, todos los servicios
#   `up dev <store> all`          → 1 instancia, todos los servicios
#   `up dev all backend`          → todas las instancias, solo backend
#   `up dev <store> backend`      → 1 instancia, solo backend
#   `up dev <store> b1 b2 b3`     → 1 instancia, varios servicios
case "$cmd_canonical" in
    up|start|stop|restart|down|nuke|build)
        if [[ "$STORE" != "all" && -z "$SERVICE" ]]; then
            # Cada token con su color para que el humano distinga
            # QUE es comando, flag, valor, subcomando.
            log_err "${C_BRIGHT_MAGENTA}${cmd_canonical}${C_RESET} ${C_DIM}<store>${C_RESET} requiere ${C_BRIGHT_GREEN}servicio${C_RESET} o ${C_BRIGHT_GREEN}'all'${C_RESET} (como servicio)."
            log_err "Si querés arrancar/detener TODOS los servicios de la instancia, usar:"
            log_err "  ${C_BRIGHT_CYAN}${0}${C_RESET} ${C_BRIGHT_MAGENTA}${cmd_canonical}${C_RESET} ${C_BRIGHT_YELLOW}${STAGE}${C_RESET} ${C_BRIGHT_BLUE}${STORE}${C_RESET} ${C_BRIGHT_GREEN}all${C_RESET}"
            log_err "Si querés solo 1 servicio (ej ${C_BRIGHT_GREEN}backend${C_RESET}):"
            log_err "  ${C_BRIGHT_CYAN}${0}${C_RESET} ${C_BRIGHT_MAGENTA}${cmd_canonical}${C_RESET} ${C_BRIGHT_YELLOW}${STAGE}${C_RESET} ${C_BRIGHT_BLUE}${STORE}${C_RESET} ${C_BRIGHT_GREEN}backend${C_RESET}"
            exit 1
        fi
        # "all" como SERVICE = todos los servicios de esta instancia.
        # Lo traducimos a vacio para que docker compose use el default
        # (levanta todos los servicios del compose).
        if [[ "$SERVICE" == "all" ]]; then
            SERVICE=""
        fi
        ;;
esac

# Para `fresh`, NO se permite store=all (crea una instancia
# específica — debe ser una sola tienda).
if [[ "$cmd_canonical" == "fresh" && "$STORE" == "all" ]]; then
    log_err "fresh requiere una tienda específica — 'all' no es válido."
    log_err "Uso: ${C_BRIGHT_CYAN}$0 fresh${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<store>${C_RESET}"
    log_err "  ${C_BRIGHT_CYAN}$0 fresh development panda-estampados-and-kitsune${C_RESET}"
    log_err "  ${C_BRIGHT_CYAN}$0 fresh production mm-boutique${C_RESET}"
    exit 1
fi

# Dispatch real: source del archivo + llamada al cmd_.
action_path="$(action_file_for "$cmd_canonical")"
if [[ -z "$action_path" || ! -f "$action_path" ]]; then
    log_err "Acción no encontrada para el comando '$cmd_canonical'"
    exit 1
fi

# shellcheck source=/dev/null
source "$action_path"

cmd_fn="cmd_${cmd_canonical//-/_}"
if ! declare -F "$cmd_fn" > /dev/null; then
    log_err "Comando '$cmd_canonical' no exporta la función $cmd_fn"
    exit 1
fi

# Exportar flags detectados para que cmd_* los consuma.
# Esto evita filtrarlos manualmente en cada call site y mantiene
# la firma cmd_*(stage, store, [svc]) estable.
export HAS_NO_CACHE
export RM_VOLUMES_SKIP_CONFIRM
export SYNC_ASSETS_DRY_RUN
export SETUP_INSTALL_CADDY
export SYNC_ASSETS_FORCE

# Summary antes de ejecutar (con stage/store ya normalizados).
show_target_summary "$cmd_canonical" "$STAGE" "$STORE" "$SERVICE"

# Dispatch al handler con (stage, store, [svc], [extra...]).
case "$cmd_canonical" in
    exec)
        "$cmd_fn" "$STAGE" "$STORE" "$SERVICE" "${EXTRA[@]}"
        ;;
    # db-restore tiene archivo como 3er posicional + flags despues.
    db-restore)
        local_restore_file="${POSITIONAL[2]}"
        # El archivo + flags (--force, --truncate, etc.) van como args.
        "$cmd_fn" "$STAGE" "$STORE" "$local_restore_file" "${EXTRA[@]}"
        ;;
    # Comandos que NO usan servicio. db-backup opera sobre el
    # servicio postgres del tenant (interno). bootstrap opera sobre
    # el backend del tenant (interno).
    db-backup|bootstrap)
        "$cmd_fn" "$STAGE" "$STORE"
        ;;
    # sync-assets y seed-db operan sobre el backend (interno) y
    # aceptan flags extra (--dry-run/--force, --seed/--sql) que se
    # pasan tal cual al wrapper.
    sync-assets|seed-db)
        "$cmd_fn" "$STAGE" "$STORE" "${EXTRA[@]}"
        ;;
    *)
        "$cmd_fn" "$STAGE" "$STORE" "$SERVICE"
        ;;
esac
