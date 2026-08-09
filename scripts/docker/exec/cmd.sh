#!/usr/bin/env bash
# scripts/docker/exec/cmd.sh
# cmd_exec <inst> <svc> <comando...>
# Ejecuta un comando dentro de un container. Útil para debugging,
# correr migraciones, inspeccionar el filesystem del container, etc.
cmd_exec() {
    if [[ $# -lt 3 ]]; then
        log_err "exec requiere: <instancia> <servicio> <comando...>"
        exit 1
    fi
    local inst="$1"; shift
    local svc="$1"; shift
    is_valid_instance "$inst" || { log_err "Instancia desconocida: $inst"; exit 1; }
    log_info "Ejecutando en ${C_BOLD}${inst}/${svc}${C_RESET}: $*"
    docker_compose "$inst" exec "$svc" "$@"
}