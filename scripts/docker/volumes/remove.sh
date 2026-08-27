#!/usr/bin/env bash
# scripts/docker/volumes/remove.sh
# cmd_rm_volumes <stage> <store> [<servicio>]
#
# ⚠ Elimina volúmenes Docker. Borra datos persistentes (BD, storage,
# caches, pnpm-store). Pide confirmación a menos que el dispatcher
# haya seteado $RM_VOLUMES_SKIP_CONFIRM=1 (cuando el usuario pasó
# `-y` en el comando).
#
# El dispatcher valida que <stage> y <store> sean correctos antes de
# llegar acá. Si store es 'all', se borran los volúmenes de las 3
# tiendas del stage.
#
# Uso:
#   rm-volumes dev panda-estampados-and-kitsune          # 1 dev
#   rm-volumes -y dev all                   # 3 dev, sin confirmación
#   rm-volumes production mm-boutique        # 1 prod
cmd_rm_volumes() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    # RM_VOLUMES_SKIP_CONFIRM lo setea el dispatcher cuando el
    # usuario pasa `-y`. Defense-in-depth: si alguien invoca este
    # script directo, también aceptamos `-y` como $4.
    local force="n"
    [[ "${RM_VOLUMES_SKIP_CONFIRM:-0}" == "1" ]] && force="y"
    [[ "${4:-}" == "-y" ]] && force="y"

    if [[ "$force" != "y" ]]; then
        log_warn "⚠  rm-volumes ELIMINA VOLÚMENES. Esto borra datos persistentes (BD, storage, caches)."
        # `read` es un builtin de bash, no un binario en /usr/bin.
        read -rp "¿Confirmás? escribí 'yes' para continuar: " confirm
        [[ "$confirm" == "yes" ]] || { log_info "Cancelado."; exit 0; }
    fi

    while IFS=$'\t' read -r inst resolved_svc; do
        local dir
        dir="$(instance_compose_dir "$inst")"
        local file
        file="$(instance_compose_file "$inst")"
        log_cmd "/usr/bin/docker compose -f ${dir}/${file} config --volumes"
        local vols
        vols="$(cd "$dir" && HOST_UID="$(/usr/bin/id -u)" HOST_GID="$(/usr/bin/id -g)" /usr/bin/docker compose -f "$file" config --volumes 2>/dev/null || true)"
        if [[ -z "$vols" ]]; then
            log_warn "  ${inst}: no hay volúmenes para borrar."
            continue
        fi
        printf "\n${C_BOLD}${C_BRIGHT_BLUE}==[ ${C_BRIGHT_CYAN}%s${C_BRIGHT_BLUE} ]==${C_RESET}\n" "$inst"
        while IFS= read -r volname; do
            [[ -z "$volname" ]] && continue
            log_info "  rm volume ${C_BRIGHT_MAGENTA}${volname}${C_RESET}"
            log_cmd "/usr/bin/docker volume rm ${volname}"
            if /usr/bin/docker volume rm "$volname" 2>/dev/null; then
                log_ok "  ${volname} borrado."
            else
                log_warn "  ${volname} no se pudo borrar (¿en uso? ¿inexistente?)."
            fi
        done <<<"$vols"
    done < <(resolve_targets "$stage" "$store" "$svc")
}
