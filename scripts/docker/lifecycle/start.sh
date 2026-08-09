#!/usr/bin/env bash
# scripts/docker/lifecycle/start.sh
# cmd_start [scope] [svc]
# Levanta containers SIN rebuild. Útil cuando la imagen ya está
# construida y solo querés reanimar containers parados.
cmd_start() {
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Levantando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans "$svc"
            log_ok "${inst}/${svc} levantado."
        else
            log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans
            log_ok "${inst} levantado."
        fi
    done < <(resolve_targets "$@")
}