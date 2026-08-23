#!/usr/bin/env bash
# scripts/docker/lifecycle/down.sh
# cmd_down <stage> <store> [<svc>]
# Detiene y ELIMINA containers (preserva volúmenes named). Para
# borrar también los volúmenes: usar cmd_nuke (separado para que
# requiera confirmación explícita).
cmd_down() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        if [[ -n "$resolved_svc" ]]; then
            log_info "Bajando container ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
            docker_compose "$inst" rm -f -s "$resolved_svc"
            log_ok "${inst}/${resolved_svc} bajado."
        else
            log_info "Bajando containers de ${C_BOLD}${inst}${C_RESET} (preserva volúmenes)…"
            docker_compose "$inst" down --remove-orphans
            log_ok "${inst} abajo."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
