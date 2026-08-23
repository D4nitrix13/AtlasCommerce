#!/usr/bin/env bash
# scripts/docker/lifecycle/start.sh
# cmd_start <stage> <store> [<svc>]
# Levanta containers SIN rebuild. Útil cuando la imagen ya está
# construida y solo querés reanimar containers parados.
cmd_start() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        ensure_env_file "$inst"
        if [[ -n "$resolved_svc" ]]; then
            log_info "Levantando ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans "$resolved_svc"
            log_ok "${inst}/${resolved_svc} levantado."
        else
            log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans
            log_ok "${inst} levantado."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
