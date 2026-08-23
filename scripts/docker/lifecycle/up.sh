#!/usr/bin/env bash
# scripts/docker/lifecycle/up.sh
# cmd_up <stage> <store> [<svc>]
# Equivalente a `docker compose up -d --build`: rebuildea las imágenes
# que cambiaron y levanta los containers en background. Convención de
# docker compose: `up` = build + start.
cmd_up() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    while IFS=$'\t' read -r inst resolved_svc; do
        ensure_env_file "$inst"
        if [[ -n "$resolved_svc" ]]; then
            log_info "Building + levantando ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans "$resolved_svc"
            log_ok "${inst}/${resolved_svc} arriba."
        else
            log_info "Building + levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans
            log_ok "${inst} arriba."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
