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

# Imprime el bloque de USO + lista de stages + stores + ejemplos
# cuando faltan argumentos posicionales. Usado por casi todos los
# comandos con args requeridos.
#
# Layout: cada ejemplo se imprime en 2 lineas — el comando solo en
# una, y la descripcion (color dim) en la siguiente con indent de 4
# espacios. Asi no se descuadra cuando un ejemplo tiene más chars que
# otro (problema classico cuando se intenta alinear comentarios en
# columna fija con anchos variables).
_print_positional_usage() {
    local cmd_canonical="$1"
    local script_path="$2"

    log_err "Uso: ${C_BRIGHT_CYAN}${script_path} <cmd>${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<store|all>${C_RESET} ${C_GREEN}[<servicio>]${C_RESET}"
    log_err "Stages: ${C_BRIGHT_CYAN}production|prod${C_RESET}, ${C_BRIGHT_CYAN}development|dev${C_RESET}"
    log_err "Stores: ${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}, ${C_BRIGHT_CYAN}mm-boutique${C_RESET}, ${C_BRIGHT_CYAN}morales-store${C_RESET}, ${C_BRIGHT_CYAN}all${C_RESET}"
    echo ""
    case "$cmd_canonical" in
        build)
            log_err "  ${C_BRIGHT_CYAN}${script_path} build no-cache dev panda-estampados-and-kitsune backend${C_RESET}"
            log_err "    ${C_DIM}rebuild sin cache de 1 target${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} build dev all backend${C_RESET}"
            log_err "    ${C_DIM}3 dev (con cache)${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} build production all${C_RESET}"
            log_err "    ${C_DIM}3 prod (con cache)${C_RESET}"
            log_err "Para help: ${C_BRIGHT_CYAN}${script_path} build help${C_RESET}"
            ;;
        rm-volumes)
            log_err "  ${C_BRIGHT_CYAN}${script_path} rm-volumes -y dev panda-estampados-and-kitsune${C_RESET}"
            log_err "    ${C_DIM}1 store, skip confirm${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} rm-volumes dev all${C_RESET}"
            log_err "    ${C_DIM}3 dev, pedir confirm${C_RESET}"
            ;;
        logs)
            log_err "  ${C_BRIGHT_CYAN}${script_path} logs dev panda-estampados-and-kitsune backend${C_RESET}"
            log_err "    ${C_DIM}svc requerido (backend|frontend|postgres|...)${C_RESET}"
            ;;
        exec)
            log_err "  ${C_BRIGHT_CYAN}${script_path} exec dev panda-estampados-and-kitsune backend bash${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} exec production mm-boutique app ls /var/www${C_RESET}"
            log_err "Para help: ${C_BRIGHT_CYAN}${script_path} exec help${C_RESET}"
            ;;
        setup)
            log_err "  ${C_BRIGHT_CYAN}${script_path} setup production all${C_RESET}"
            log_err "    ${C_DIM}3 prod (incluye all)${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} setup development panda-estampados-and-kitsune${C_RESET}"
            log_err "    ${C_DIM}1 dev${C_RESET}"
            ;;
        fresh)
            log_err "  ${C_BRIGHT_CYAN}${script_path} fresh development panda-estampados-and-kitsune${C_RESET}"
            log_err "    ${C_DIM}1 instancia (store obligatorio)${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} fresh production mm-boutique${C_RESET}"
            log_err "    ${C_DIM}1 instancia prod${C_RESET}"
            ;;
        *)
            log_err "  ${C_BRIGHT_CYAN}${script_path} ${cmd_canonical} dev panda-estampados-and-kitsune${C_RESET}"
            log_err "    ${C_DIM}1 instancia${C_RESET}"
            log_err "  ${C_BRIGHT_CYAN}${script_path} ${cmd_canonical} dev all${C_RESET}"
            log_err "    ${C_DIM}3 dev${C_RESET}"
            log_err "Para help: ${C_BRIGHT_CYAN}${script_path} ${cmd_canonical} help${C_RESET}"
            ;;
    esac
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
#   build      → no-cache    (en cualquier posición)
#   rm-volumes → -y          (skip confirmation)
HAS_NO_CACHE=0
RM_VOLUMES_SKIP_CONFIRM=0
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
                no-cache)      HAS_NO_CACHE=1 ;;
                -y)            RM_VOLUMES_SKIP_CONFIRM=1 ;;
                --dry-run)     SYNC_ASSETS_DRY_RUN=1 ;;
                --force)       SYNC_ASSETS_FORCE=1 ;;
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
            log_err "'$cmd_canonical <store>' requiere servicio o 'all' (como servicio)."
            log_err "Si querés arrancar/detener TODOS los servicios de la instancia, usar:"
            log_err "  ${C_BRIGHT_CYAN}$0 $cmd_canonical $STAGE $STORE all${C_RESET}"
            log_err "Si querés solo 1 servicio (ej backend):"
            log_err "  ${C_BRIGHT_CYAN}$0 $cmd_canonical $STAGE $STORE backend${C_RESET}"
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
