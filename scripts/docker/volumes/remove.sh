#!/usr/bin/env bash
# scripts/docker/volumes/remove.sh
# cmd_rm_volumes [-y] <área> [<servicio>]
#
# ⚠ Elimina volúmenes Docker. Borra datos persistentes (BD, storage,
# caches, pnpm-store). Pide confirmación a menos que se pase `-y`.
#
# El <área> es REQUERIDA — `rm-volumes` solo es válido si se pasa
# un scope explícito. Tiene sentido doble: es destructivo + el
# output por instancia es masivo. Default to all = peligro.
#
# Uso:
#   rm-volumes -y dev backend              # 3 dev, backend
#   rm-volumes -y development:panda-estampados  # 1 instancia
#   rm-volumes -y all                      # ⚠ las 6 (con skip confirmación)
cmd_rm_volumes() {
    local force="n"
    [[ "${1:-}" == "-y" ]] && { force="y"; shift; }

    # Validación upfront del área. Si no hay scope después del flag,
    # error con hint — nunca defaultear a 'all' en un comando
    # destructivo.
    if [[ -z "${1:-}" ]]; then
        log_err "Falta <área>. Pasar una explícita:"
        log_err "  ${C_BRIGHT_CYAN}-y all${C_RESET}                    → ${C_BRIGHT_RED}las 6 (⚠)${C_RESET}"
        log_err "  ${C_BRIGHT_CYAN}-y dev${C_RESET}                    → 3 dev"
        log_err "  ${C_BRIGHT_CYAN}-y prod${C_RESET}                   → 3 prod"
        log_err "Para más: ${C_BRIGHT_CYAN}$0 rm-volumes help${C_RESET}"
        return 1
    fi

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
    done < <(resolve_targets "$@")
}
