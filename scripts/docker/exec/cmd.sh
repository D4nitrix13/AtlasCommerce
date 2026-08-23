#!/usr/bin/env bash
# scripts/docker/exec/cmd.sh
# cmd_exec <stage> <store> <servicio> <comando...>
# Ejecuta un comando dentro de un container. Útil para debugging,
# correr migraciones, inspeccionar el filesystem del container, etc.
cmd_exec() {
    if [[ $# -lt 4 ]]; then
        # Defense-in-depth: el dispatcher ya exige MIN_POS=4.
        log_err "exec requiere: <stage> <store> <servicio> <comando...>"
        log_err "Uso: cmd_exec <stage> <store> <servicio> <comando...>"
        exit 1
    fi
    local stage="$1"
    local store="$2"
    local svc="$3"
    shift 3

    # resolve_targets emite líneas `<stage>:<store>\t<svc>`. Para exec
    # queremos la primera (y normalmente única) instancia del target.
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" "$svc" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        exit 1
    fi

    log_info "Ejecutando en ${C_BOLD}${first_inst}/${svc}${C_RESET}: $*"
    # shellcheck disable=SC2086
    docker_compose "$first_inst" exec "$svc" "$@"
}
