#!/usr/bin/env bash
# scripts/docker/lifecycle/restart.sh
# cmd_restart <stage> <store> [<svc>]
# Reinicia containers sin rebuild. Útil después de cambiar .env o
# para refrescar estado sin perder imágenes ni volúmenes.
cmd_restart() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        if [[ -n "$resolved_svc" ]]; then
            log_info "Reiniciando ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
            docker_compose "$inst" restart "$resolved_svc"
            log_ok "${inst}/${resolved_svc} reiniciado."
        else
            ensure_env_file "$inst"
            log_info "Reiniciando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" restart
            log_ok "${inst} reiniciado."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
