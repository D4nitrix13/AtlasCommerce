#!/usr/bin/env bash
# scripts/docker/lifecycle/up.sh
# cmd_up [scope] [svc]
# Equivalente a `docker compose up -d --build`: rebuildea las imágenes
# que cambiaron y levanta los containers en background. Convención de
# docker compose: `up` = build + start.
cmd_up() {
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Building + levantando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans "$svc"
            log_ok "${inst}/${svc} arriba."
        else
            log_info "Building + levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans
            log_ok "${inst} arriba."
        fi
    done < <(resolve_targets "$@")
}