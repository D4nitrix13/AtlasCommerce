#!/usr/bin/env bash
# scripts/docker/lifecycle/down.sh
# cmd_down [scope] [svc]
# Detiene y ELIMINA containers (preserva volúmenes named). Para
# borrar también los volúmenes: usar cmd_nuke (separado para que
# requiera confirmación explícita).
cmd_down() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Bajando container ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" rm -f -s "$svc"
            log_ok "${inst}/${svc} bajado."
        else
            log_info "Bajando containers de ${C_BOLD}${inst}${C_RESET} (preserva volúmenes)…"
            docker_compose "$inst" down --remove-orphans
            log_ok "${inst} abajo."
        fi
    done < <(resolve_targets "$@")
}