#!/usr/bin/env bash
# scripts/docker/volumes/list.sh
# cmd_volumes <stage> <store> [<servicio>]
#
# Lista volúmenes Docker (driver, mountpoint, size) de los targets
# solicitados.
#
# <stage> y <store> son REQUERIDOS — el dispatcher los valida antes
# de llegar acá (si store es 'all', se listan todas las del stage).
#
# Uso:
#   volumes dev all                          # 3 dev
#   volumes production all                  # 3 prod
#   volumes development panda-estampados    # 1 instancia dev
#   volumes dev all backend                  # servicio backend de las 3 dev
cmd_volumes() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    log_info "Listando volúmenes Docker de los targets solicitados…"
    # docker compose config --volumes imprime los volúmenes nombrados
    # que la instancia declara. Combinamos con docker volume inspect
    # para ver tamaño + driver + mountpoint reales.
    while IFS=$'\t' read -r inst resolved_svc; do
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
    done < <(resolve_targets "$stage" "$store" "$svc")
}
