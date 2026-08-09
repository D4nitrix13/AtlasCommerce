#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker-stack.sh — entry point del orquestador Docker
# ------------------------------------------------------------
# Dispatcher delgado: parsea el comando, carga la lib compartida
# y delega al archivo de acción correspondiente bajo scripts/docker/.
#
# Cada acción vive en su propia carpeta:
#   scripts/docker/up/up.sh          → cmd_up
#   scripts/docker/start/start.sh    → cmd_start
#   scripts/docker/volumes/list.sh   → cmd_volumes
#   scripts/docker/help/show.sh      → cmd_help
#   etc.
#
# La lib compartida (colores, logging, resolve, docker_compose) está
# en scripts/docker/_lib/ y se carga UNA sola vez al inicio.
#
# Convenciones aplicadas (ver scripts/README.md):
#   - Rutas absolutas para binarios (/usr/bin/docker, /usr/bin/id)
#     para evitar PATH hijacking.
#   - Archivos cortos, una responsabilidad cada uno.
#   - Ningún script excede ~100 líneas salvo help.
#
# Uso:
#   ./scripts/docker-stack.sh <comando> [args...]
#
# Requiere: docker compose v2 (>= 5.x), bash 4+.
# ------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && /usr/bin/pwd)"
DOCKER_DIR="${SCRIPT_DIR}/docker"
LIB_DIR="${DOCKER_DIR}/_lib"

# Cargar lib compartida UNA vez. El orden importa (colors →
# logging → resolve → docker_compose); está documentado en all.sh.
# shellcheck source=scripts/docker/_lib/all.sh
source "${LIB_DIR}/all.sh"

# Dispatcher: cada acción se carga con source para que su función
# `cmd_<acción>` quede definida en el scope del dispatcher y
# podamos invocarla inmediatamente.
cmd="${1:-help}"
shift || true

case "$cmd" in
    # Aliases cortos para uso frecuente.
    b)        source "${DOCKER_DIR}/lifecycle/build.sh";       cmd_build "$@" ;;
    rs)       source "${DOCKER_DIR}/lifecycle/restart.sh";     cmd_restart "$@" ;;
    ls)       source "${DOCKER_DIR}/inspection/ps.sh";         cmd_ps "$@" ;;
    psv|info) source "${DOCKER_DIR}/inspection/info.sh";       cmd_info "$@" ;;
    vol)      source "${DOCKER_DIR}/volumes/list.sh";         cmd_volumes "$@" ;;
    rmvol)    source "${DOCKER_DIR}/volumes/remove.sh";        cmd_rm_volumes "$@" ;;

    # Lifecycle.
    up)        source "${DOCKER_DIR}/lifecycle/up.sh";          cmd_up "$@" ;;
    start)     source "${DOCKER_DIR}/lifecycle/start.sh";       cmd_start "$@" ;;
    stop)      source "${DOCKER_DIR}/lifecycle/stop.sh";        cmd_stop "$@" ;;
    restart)   source "${DOCKER_DIR}/lifecycle/restart.sh";     cmd_restart "$@" ;;
    build)     source "${DOCKER_DIR}/lifecycle/build.sh";       cmd_build "$@" ;;
    down)      source "${DOCKER_DIR}/lifecycle/down.sh";        cmd_down "$@" ;;
    nuke)      source "${DOCKER_DIR}/lifecycle/nuke.sh";        cmd_nuke "$@" ;;

    # Inspección.
    status)    source "${DOCKER_DIR}/inspection/status.sh";     cmd_status "$@" ;;
    ps)        source "${DOCKER_DIR}/inspection/ps.sh";         cmd_ps "$@" ;;

    # Logs y ejecución.
    logs)      source "${DOCKER_DIR}/inspection/logs.sh";       cmd_logs "$@" ;;
    exec)      source "${DOCKER_DIR}/exec/cmd.sh";              cmd_exec "$@" ;;

    # Volúmenes.
    volumes)   source "${DOCKER_DIR}/volumes/list.sh";         cmd_volumes "$@" ;;
    rm-volumes) source "${DOCKER_DIR}/volumes/remove.sh";       cmd_rm_volumes "$@" ;;

    # Setup y ayuda.
    setup)     source "${DOCKER_DIR}/setup/first-install.sh";   cmd_setup "$@" ;;
    hosts)     source "${DOCKER_DIR}/setup/hosts.sh";           cmd_hosts "$@" ;;
    caddy)     source "${DOCKER_DIR}/setup/caddy.sh";           cmd_caddy "$@" ;;
    help|-h|--help) source "${DOCKER_DIR}/help/show.sh";      cmd_help ;;

    *)
        log_err "Comando desconocido: $cmd"
        echo
        source "${DOCKER_DIR}/help/show.sh"
        cmd_help
        exit 1
        ;;
esac