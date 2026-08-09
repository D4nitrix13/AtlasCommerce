#!/usr/bin/env bash
# scripts/docker/inspection/status.sh
# cmd_status [scope] [svc]
# Estado de cada servicio (compose ps agrupado por instancia).
cmd_status() {
    while IFS=$'\t' read -r inst svc; do
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        if [[ -n "$svc" ]]; then
            docker_compose "$inst" ps "$svc"
        else
            docker_compose "$inst" ps
        fi
    done < <(resolve_targets "$@")
}