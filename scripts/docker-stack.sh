#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker-stack.sh — entry point del orquestador Docker
# ------------------------------------------------------------
# Dispatcher delgado con detección de "help" por subcomando y
# resumen de área + servicios antes de cada ejecución.
#
# Comandos disponibles (canonical):
#   Lifecycle   : up start stop restart build down nuke
#   Inspección  : status ps info logs
#   Volumes     : volumes rm-volumes
#   Exec        : exec
#   Setup       : setup hosts caddy
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
#   ./scripts/docker-stack.sh <comando> [<área>] [<servicio>]
#   ./scripts/docker-stack.sh <comando> help          # help del comando
#   ./scripts/docker-stack.sh <comando> <área> help   # help filtrado
#   ./scripts/docker-stack.sh help                    # help global
#
# Áreas canónicas:
#   all       → todas las 6 instancias (3 prod + 3 dev)
#   prod      → las 3 prod
#   dev       → las 3 dev
#   <store>   → 1 instancia (default: production:<store>)
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
        hosts)       printf '%s\n' "${DOCKER_DIR}/setup/hosts.sh" ;;
        caddy)       printf '%s\n' "${DOCKER_DIR}/setup/caddy.sh" ;;
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
        exec|setup|hosts|caddy|help|\
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

# Primer arg = help/-h/--help → global help.
case "$cmd_raw" in
    help|-h|--help)
        # shellcheck source=scripts/docker/help/show.sh
        source "${DOCKER_DIR}/help/show.sh"
        cmd_help
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
if args_contain_help "${@:2}"; then
    help_args=()
    for arg in "${@:2}"; do
        case "$arg" in
            help|-h|--help) break ;;
            *) help_args+=("$arg") ;;
        esac
    done
    # Cargar el registry de help per-comando.
    registry="${DOCKER_DIR}/help/registry.sh"
    if [[ -f "$registry" ]]; then
        # shellcheck source=scripts/docker/help/registry.sh
        source "$registry"
    fi
    # Cargar también el show.sh por si necesitamos cmd_help_como_fallback.
    # shellcheck source=scripts/docker/help/show.sh
    source "${DOCKER_DIR}/help/show.sh"
    help_fn="cmd_help_${cmd_canonical//-/_}"
    if declare -F "$help_fn" > /dev/null; then
        "$help_fn" "${help_args[@]}"
        exit 0
    fi
    # Fallback: help global filtrado por comando.
    log_warn "No hay help específica para '$cmd_canonical' — mostrando help global."
    echo
    cmd_help "$cmd_canonical"
    exit 0
fi

# Dispatch normal: source del archivo + llamada al cmd_.
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

# Las funciones cmd_* (up, start, ..., volumes, rm-volumes, exec, ...)
# esperan args SIN el primer elemento (el nombre del comando). Ese es
# el convenio de resolve_targets: arg1=área, arg2=servicio.
shift  # descarta "$cmd_raw"

# Pre-validar el área (sin consumir la salida de resolve_targets) para
# evitar summaries optimistas antes de un error inevitable.
case "$cmd_canonical" in
    setup|hosts|caddy|help)
        "$cmd_fn" "$@"
        ;;
    # Comandos que REQUIEREN <area> explicito (no default a all).
    # Justificacion: su output por instancia es masivo (volumes:
    # 8+ volúmenes; rm-volumes: además destructivo). El default a all
    # generaba escapes de output que el usuario no esperaba. Mejor
    # forzarlo a ser explicito, con hint de qué pasar.
    volumes|rm-volumes)
        # rm-volumes soporta flag opcional -y antes del area.
        area_check="${1:-}"
        if [[ "$cmd_canonical" == "rm-volumes" && "$area_check" == "-y" ]]; then
            area_check="${2:-}"
        fi
        if [[ -z "$area_check" ]]; then
            log_err "<area> es requerido. Para ver TODOS pasar 'all' explícitamente."
            if [[ "$cmd_canonical" == "rm-volumes" ]]; then
                log_err "  ${C_BRIGHT_CYAN}$0 rm-volumes -y all${C_RESET}                ${C_DIM}# todas (skip confirm)${C_RESET}"
                log_err "  ${C_BRIGHT_CYAN}$0 rm-volumes -y dev${C_RESET}                ${C_DIM}# 3 dev${C_RESET}"
                log_err "  ${C_BRIGHT_CYAN}$0 rm-volumes -y dev backend${C_RESET}        ${C_DIM}# 1 target (destructivo!)${C_RESET}"
                log_err "Para help: ${C_BRIGHT_CYAN}$0 rm-volumes help${C_RESET}"
            else
                log_err "  ${C_BRIGHT_CYAN}$0 volumes all${C_RESET}                    ${C_DIM}# 6 instancias${C_RESET}"
                log_err "  ${C_BRIGHT_CYAN}$0 volumes dev${C_RESET}                    ${C_DIM}# 3 dev${C_RESET}"
                log_err "  ${C_BRIGHT_CYAN}$0 volumes prod${C_RESET}                   ${C_DIM}# 3 prod${C_RESET}"
                log_err "  ${C_BRIGHT_CYAN}$0 volumes development:panda-and-kitsune${C_RESET}  ${C_DIM}# 1 instancia${C_RESET}"
                log_err "Para help: ${C_BRIGHT_CYAN}$0 volumes help${C_RESET}"
            fi
            exit 1
        fi
        show_target_summary "$cmd_canonical" "$@"
        "$cmd_fn" "$@"
        ;;
    *)
        # Pre-validate (validate_area imprime su propio error y devuelve 1).
        if ! validate_area "${1:-}"; then
            exit 1
        fi
        show_target_summary "$cmd_canonical" "$@"
        "$cmd_fn" "$@"
        ;;
esac
