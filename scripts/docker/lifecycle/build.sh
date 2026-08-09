#!/usr/bin/env bash
# scripts/docker/lifecycle/build.sh
# cmd_build [scope] [svc]
# Reconstruye imágenes con --no-cache (sin usar cache de layers).
# Útil cuando cambia algo en un Dockerfile o cuando el cache quedó
# stale y queremos asegurarnos de tener el binario fresco.
cmd_build() {
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Building ${C_BOLD}${inst}/${svc}${C_RESET} (no cache)…"
            docker_compose "$inst" build --no-cache "$svc"
            log_ok "${inst}/${svc} construido."
        else
            log_info "Building ${C_BOLD}${inst}${C_RESET} (no cache)…"
            docker_compose "$inst" build --no-cache
            log_ok "${inst} construido."
        fi
    done < <(resolve_targets "$@")
}