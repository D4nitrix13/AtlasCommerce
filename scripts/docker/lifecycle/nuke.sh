#!/usr/bin/env bash
# scripts/docker/lifecycle/nuke.sh
# cmd_nuke <stage> <store> [<svc>]
# ⚠ PELIGROSO: baja containers Y elimina volúmenes. Borra la base
# de datos completa. Pide confirmación explícita ("yes") antes de
# proceder. No se puede deshacer.
cmd_nuke() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"
    log_warn "⚠  nuke ELIMINA VOLÚMENES. Esto borra la base de datos completa."
    /usr/bin/read -rp "¿Confirmás? escribí 'yes' para continuar: " confirm
    [[ "$confirm" == "yes" ]] || { log_info "Cancelado."; exit 0; }
    while IFS=$'\t' read -r inst resolved_svc; do
        if [[ -n "$resolved_svc" ]]; then
            log_info "Nuke de ${C_BOLD}${inst}/${resolved_svc}${C_RESET} (container + volumen)…"
            docker_compose "$inst" rm -f -s -v "$resolved_svc"
            log_ok "${inst}/${resolved_svc} nuked."
        else
            log_info "Nuke de ${C_BOLD}${inst}${C_RESET} (containers + volúmenes)…"
            docker_compose "$inst" down --volumes --remove-orphans
            log_ok "${inst} nuked."
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
