#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/_lib/summary.sh
# ------------------------------------------------------------
# show_target_summary <cmd_name> <área> [<servicio>]
# validate_area <área>
#
# show_target_summary imprime una línea-resumen antes de ejecutar
# un comando que usa resolve_targets.
#
# validate_area chequea que el área pasada sea reconocible por
# resolve_targets. Devuelve 0 si es válida, 1 si no (con mensaje
# de error en stderr via log_err).
#
# Argumentos del summary:
#   - cmd_name: nombre del comando activo
#   - área: all | prod | dev | production:<store> | development:<store>
#   - servicio (opcional)
#
# Imprime: nombre, área, n de instancias target (1..6).
#
# Depende de: resolve.sh (ALL_INSTANCES, PROD_INSTANCES, DEV_INSTANCES,
# is_valid_instance), logging.sh (log_info, log_err), colors.sh (C_*).
# Se carga desde all.sh, una sola vez por sesión.
# ------------------------------------------------------------

# Valida el área. Devuelve 0/1.
validate_area() {
    local arg="${1:-all}"
    case "$arg" in
        all|production|prod|development|dev|"")
            return 0
            ;;
        production:*|development:*)
            return 0
            ;;
    esac
    if is_valid_instance "$arg"; then
        return 0
    fi
    log_err "Instancia o stage desconocido: ${arg}. Válidas: all, production|prod, development|dev, o: ${ALL_INSTANCES[*]}"
    return 1
}

show_target_summary() {
    local cmd_name="${1:-cmd}"
    local arg="${2:-all}"
    local svc="${3:-}"

    local label
    local count
    local plural

    case "$arg" in
        all|"")
            label="all"
            count=$(printf '%s\n' "${ALL_INSTANCES[@]}" | /usr/bin/wc -l | /usr/bin/tr -d ' ')
            ;;
        production|prod)
            label="prod"
            count=${#PROD_INSTANCES[@]}
            ;;
        development|dev)
            label="dev"
            count=${#DEV_INSTANCES[@]}
            ;;
        production:*|development:*)
            label="$arg"
            count=1
            ;;
        *)
            # Nombre de store suelto → default = production:<store>
            label="production:${arg}"
            count=1
            ;;
    esac

    if [[ "$count" -gt 1 ]]; then
        plural="instancias"
    else
        plural="instancia"
    fi

    if [[ -n "$svc" ]]; then
        log_info "[${cmd_name}] ${C_BOLD}área=${C_RESET}${label} · ${C_BOLD}servicio=${C_RESET}${svc} · ${C_BOLD}1${C_RESET} target"
    else
        log_info "[${cmd_name}] ${C_BOLD}área=${C_RESET}${label} · ${C_BOLD}n=${C_RESET}${count} ${plural}"
    fi
}
