#!/usr/bin/env bash
# scripts/docker/lifecycle/up.sh
# cmd_up <stage> <store> [<svc>]
# Equivalente a `docker compose up -d --build`: rebuildea las imágenes
# que cambiaron y levanta los containers en background. Convención de
# docker compose: `up` = build + start.
#
# Flag --no-build (seteado por el dispatcher en $HAS_NO_BUILD): reusa
# las imágenes existentes en lugar de rebuildear. Útil cuando la build
# de otro servicio está rota y querés recrear solo 1 container, o
# cuando ya construiste la imagen vía `build` por separado. Se combina
# internamente con --force-recreate porque sin build, compose no
# detecta cambios y no recrea el container.
cmd_up() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    # HAS_NO_BUILD viene del dispatcher (1 si el usuario pasó
    # `no-build` en cualquier posición). Defense-in-depth: si el script
    # se invoca sin dispatcher (ej: tests), aceptamos el flag también
    # vía $4 — mismo patrón que `build.sh` con `--no-cache`.
    local no_build="${HAS_NO_BUILD:-0}"
    if [[ $# -ge 4 && "$4" == "no-build" ]]; then
        no_build=1
    fi

    while IFS=$'\t' read -r inst resolved_svc; do
        ensure_env_file "$inst"
        if [[ "$no_build" -eq 1 ]]; then
            if [[ -n "$resolved_svc" ]]; then
                log_info "Levantando ${C_BOLD}${inst}/${resolved_svc}${C_RESET} ${C_DIM}(sin build, forzar recrear)${C_RESET}…"
                docker_compose "$inst" up -d --no-build --force-recreate --remove-orphans "$resolved_svc"
                log_ok "${inst}/${resolved_svc} arriba (sin build)."
            else
                log_info "Levantando ${C_BOLD}${inst}${C_RESET} ${C_DIM}(sin build, forzar recrear)${C_RESET}…"
                docker_compose "$inst" up -d --no-build --force-recreate --remove-orphans
                log_ok "${inst} arriba (sin build)."
            fi
        else
            if [[ -n "$resolved_svc" ]]; then
                log_info "Building + levantando ${C_BOLD}${inst}/${resolved_svc}${C_RESET}…"
                docker_compose "$inst" up -d --build --remove-orphans "$resolved_svc"
                log_ok "${inst}/${resolved_svc} arriba."
            else
                log_info "Building + levantando ${C_BOLD}${inst}${C_RESET}…"
                docker_compose "$inst" up -d --build --remove-orphans
                log_ok "${inst} arriba."
            fi
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
