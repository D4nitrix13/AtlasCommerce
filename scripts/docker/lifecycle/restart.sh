#!/usr/bin/env bash
# scripts/docker/lifecycle/restart.sh
# cmd_restart [scope] [svc]
# Reinicia containers sin rebuild. Útil después de cambiar .env o
# para refrescar estado sin perder imágenes ni volúmenes.
cmd_restart() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Reiniciando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" restart "$svc"
            log_ok "${inst}/${svc} reiniciado."
        else
            ensure_env_file "$inst"
            log_info "Reiniciando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" restart
            log_ok "${inst} reiniciado."
        fi
    done < <(resolve_targets "$@")
}