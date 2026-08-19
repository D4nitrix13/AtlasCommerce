#!/usr/bin/env bash
# scripts/docker/volumes/list.sh
# cmd_volumes <área> [<servicio>]
#
# Lista volúmenes Docker (driver, mountpoint, size) de los targets
# solicitados.
#
# El <área> es REQUERIDA — para ver TODOS los volúmenes hay que
# pasar explícitamente `all`. Esto evita que un `volumes` sin args
# escupa output de las 6 instancias sin que el usuario lo notara.
#
# Uso:
#   volumes all                          # 6 instancias (3 prod + 3 dev)
#   volumes dev                          # 3 dev
#   volumes production                   # 3 prod
#   volumes development:panda-estampados # 1 instancia dev
#   volumes dev backend                  # del servicio backend de las 3 dev
cmd_volumes() {
    # Validación upfront: si no hay <área>, error con hint en lugar
    # de defaultear silenciosamente a 'all'. El usuario debe EXPLICITAR
    # qué quiere ver.
    if [[ -z "${1:-}" ]]; then
        log_err "Falta <área>. Pasar una explícita:"
        log_err "  ${C_BRIGHT_CYAN}all${C_RESET}                       → 6 instancias (3 prod + 3 dev)"
        log_err "  ${C_BRIGHT_CYAN}dev${C_RESET}                       → 3 dev"
        log_err "  ${C_BRIGHT_CYAN}prod${C_RESET}                      → 3 prod"
        log_err "  ${C_BRIGHT_CYAN}development:<store>${C_RESET}        → 1 instancia dev"
        log_err "Para más: ${C_BRIGHT_CYAN}$0 volumes help${C_RESET}"
        return 1
    fi

    log_info "Listando volúmenes Docker de los targets solicitados…"
    # docker compose config --volumes imprime los volúmenes nombrados
    # que la instancia declara. Combinamos con docker volume inspect
    # para ver tamaño + driver + mountpoint reales.
    while IFS=$'\t' read -r inst svc; do
        local dir
        dir="$(instance_compose_dir "$inst")"
        local file
        file="$(instance_compose_file "$inst")"
        log_cmd "/usr/bin/docker compose -f ${dir}/${file} config --volumes"
        local vols
        vols="$(cd "$dir" && HOST_UID="$(/usr/bin/id -u)" HOST_GID="$(/usr/bin/id -g)" /usr/bin/docker compose -f "$file" config --volumes 2>/dev/null || true)"
        if [[ -z "$vols" ]]; then
            log_warn "  ${inst}: no se encontraron volúmenes declarados."
            continue
        fi
        printf "\n${C_BOLD}${C_BRIGHT_BLUE}==[ ${C_BRIGHT_CYAN}%s${C_BRIGHT_BLUE} ]==${C_RESET}\n" "$inst"
        while IFS= read -r volname; do
            [[ -z "$volname" ]] && continue
            local info
            info="$(/usr/bin/docker volume inspect "$volname" --format '{{.Driver}}\t{{.Mountpoint}}\t{{.Size}}' 2>/dev/null || printf "n/a\tn/a\tn/a")"
            local driver mountpoint size
            IFS=$'\t' read -r driver mountpoint size <<<"$info"
            printf "  ${C_BRIGHT_MAGENTA}%-50s${C_RESET}  ${C_DIM}driver=${C_RESET}%s  ${C_DIM}size=${C_RESET}%s\n" \
                "$volname" "$driver" "$size"
        done <<<"$vols"
    done < <(resolve_targets "$@")
}
