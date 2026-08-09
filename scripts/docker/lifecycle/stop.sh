#!/usr/bin/env bash
# scripts/docker/lifecycle/stop.sh
# cmd_stop [scope] [svc]
# Detiene containers pero NO los elimina. Los volúmenes quedan
# intactos y la imagen persiste. Para volver a levantarlos: cmd_start.
cmd_stop() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Deteniendo ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" stop "$svc"
            log_ok "${inst}/${svc} detenido (container preservado)."
        else
            log_info "Deteniendo ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" stop
            log_ok "${inst} detenido (containers preservados)."
        fi
    done < <(resolve_targets "$@")
}