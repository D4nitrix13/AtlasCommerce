#!/usr/bin/env bash
# scripts/docker/inspection/status.sh
# cmd_status <stage> <store> [<svc>]
# Estado de cada servicio (compose ps agrupado por instancia).
cmd_status() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        if [[ -n "$resolved_svc" ]]; then
            docker_compose "$inst" ps "$resolved_svc"
        else
            docker_compose "$inst" ps
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
