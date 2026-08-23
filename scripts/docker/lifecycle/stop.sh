#!/usr/bin/env bash
# scripts/docker/lifecycle/stop.sh
# cmd_stop <stage> <store> [<svc>]
# Detiene containers pero NO los elimina. Los volúmenes quedan
# intactos y la imagen persiste. Para volver a levantarlos: cmd_start.
cmd_stop() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        if [[ -n "$resolved_svc" ]]; then
            log_info "Deteniendo ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
            docker_compose "$inst" stop "$resolved_svc"
            log_ok "${inst}/${resolved_svc} detenido (container preservado)."
        else
            log_info "Deteniendo ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" stop
            log_ok "${inst} detenido (containers preservados)."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
