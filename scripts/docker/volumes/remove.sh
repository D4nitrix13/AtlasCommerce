#!/usr/bin/env bash
# scripts/docker/volumes/remove.sh
# cmd_rm_volumes [-y] [scope] [svc]
# ⚠ Elimina volúmenes Docker. Borra datos persistentes (BD, storage,
# caches, pnpm-store). Pide confirmación a menos que se pase `-y`.
#
# Uso:
#   rm-volumes -y dev backend           # borra volúmenes del backend de las 3 dev
#   rm-volumes development:panda-and-kitsune # borra TODO el state de panda dev
cmd_rm_volumes() {
    local force="n"
    [[ "${1:-}" == "-y" ]] && { force="y"; shift; }

    if [[ "$force" != "y" ]]; then
        log_warn "⚠  rm-volumes ELIMINA VOLÚMENES. Esto borra datos persistentes (BD, storage, caches)."
        /usr/bin/read -rp "¿Confirmás? escribí 'yes' para continuar: " confirm
        [[ "$confirm" == "yes" ]] || { log_info "Cancelado."; exit 0; }
    fi

    while IFS=$'\t' read -r inst svc; do
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
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        while IFS= read -r volname; do
            [[ -z "$volname" ]] && continue
            log_info "  rm volume ${C_CYAN}${volname}${C_RESET}"
            log_cmd "/usr/bin/docker volume rm ${volname}"
            if /usr/bin/docker volume rm "$volname" 2>/dev/null; then
                log_ok "  ${volname} borrado."
            else
                log_warn "  ${volname} no se pudo borrar (¿en uso? ¿inexistente?)."
            fi
        done <<<"$vols"
    done < <(resolve_targets "$@")
}