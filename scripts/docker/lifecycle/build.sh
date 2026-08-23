#!/usr/bin/env bash
# scripts/docker/lifecycle/build.sh
# cmd_build <stage> <store> [<svc>]
#
# Reconstruye imágenes Docker usando el cache de layers por default
# (rápido: solo reconstruye los stages que cambiaron). El flag
# `no-cache` lo lee del env var $HAS_NO_CACHE, seteado por el
# dispatcher; si está activo, forza rebuild completo sin cache.
#
# Útil cuando el cache quedó stale o cambió algo en una imagen base
# que no se detecta vía checksum del Dockerfile.
#
# El orquestador traduce el flag del usuario (`no-cache`) al flag
# nativo de docker compose (`--no-cache`) en la llamada interna.
cmd_build() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    # HAS_NO_CACHE viene del dispatcher (1 si el usuario pasó
    # `no-cache` en cualquier posición). Defense-in-depth: si el
    # script se invoca sin dispatcher (ej: tests), aceptamos el flag
    # también vía $4 para no romper ese caso de uso.
    local no_cache="${HAS_NO_CACHE:-0}"
    if [[ $# -ge 4 && "$4" == "no-cache" ]]; then
        no_cache=1
    fi

    while IFS=$'\t' read -r inst resolved_svc; do
        ensure_env_file "$inst"
        if [[ "$no_cache" -eq 1 ]]; then
            if [[ -n "$resolved_svc" ]]; then
                log_info "Building ${C_BOLD}${inst}/${resolved_svc}${C_RESET} ${C_DIM}(sin cache)${C_RESET}…"
                docker_compose "$inst" build --no-cache "$resolved_svc"
                log_ok "${inst}/${resolved_svc} construido (sin cache)."
            else
                log_info "Building ${C_BOLD}${inst}${C_RESET} ${C_DIM}(sin cache)${C_RESET}…"
                docker_compose "$inst" build --no-cache
                log_ok "${inst} construido (sin cache)."
            fi
        else
            if [[ -n "$resolved_svc" ]]; then
                log_info "Building ${C_BOLD}${inst}/${resolved_svc}${C_RESET} ${C_DIM}(con cache)${C_RESET}…"
                docker_compose "$inst" build "$resolved_svc"
                log_ok "${inst}/${resolved_svc} construido."
            else
                log_info "Building ${C_BOLD}${inst}${C_RESET} ${C_DIM}(con cache)${C_RESET}…"
                docker_compose "$inst" build
                log_ok "${inst} construido."
            fi
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
