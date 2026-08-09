#!/usr/bin/env bash
# scripts/docker/volumes/list.sh
# cmd_volumes [scope] [svc]
# Lista volúmenes Docker (driver, mountpoint, size) de los targets
# solicitados. Filtra los volúmenes por nombre de proyecto compose.
#
# Uso:
#   volumes                              # todos los volúmenes de las 6 instancias
#   volumes dev                          # volúmenes de las 3 instancias dev
#   volumes development:panda-and-kitsune # solo los de panda-and-kitsune dev
#   volumes dev backend                  # del servicio backend de las 3 dev
cmd_volumes() {
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
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        while IFS= read -r volname; do
            [[ -z "$volname" ]] && continue
            local info
            info="$(/usr/bin/docker volume inspect "$volname" --format '{{.Driver}}\t{{.Mountpoint}}\t{{.Size}}' 2>/dev/null || printf "n/a\tn/a\tn/a")"
            local driver mountpoint size
            IFS=$'\t' read -r driver mountpoint size <<<"$info"
            printf "  ${C_CYAN}%-50s${C_RESET}  driver=%s  size=%s\n" \
                "$volname" "$driver" "$size"
        done <<<"$vols"
    done < <(resolve_targets "$@")
}