#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/_lib/summary.sh
# ------------------------------------------------------------
# show_target_summary <cmd_name> <stage> <store> [svc]
# validate_stage_store <stage> <store>
#
# show_target_summary imprime una línea-resumen antes de ejecutar
# un comando que usa resolve_targets.
#
# validate_stage_store chequea que los argumentos (stage, store)
# sean reconocibles por resolve_targets. Devuelve 0 si son válidos,
# 1 si no (con mensaje de error en stderr via log_err).
#
# Argumentos del summary:
#   - cmd_name: nombre del comando activo
#   - stage: production | development (alias: prod | dev)
#   - store: uno de STORES, o 'all'
#   - servicio (opcional)
#
# Imprime: nombre del comando, stage, store, n de instancias
# target (1..3), servicio si aplica.
#
# Depende de: resolve.sh (STAGES, STORES, ALL_INSTANCES, is_valid_store),
# logging.sh (log_info, log_err), colors.sh (C_*).
# Se carga desde all.sh, una sola vez por sesión.
# ------------------------------------------------------------

# Valida el par (stage, store). Devuelve 0/1.
# Permite stage o store vacíos (algunos dispatchers pre-validan antes
# de llegar acá, otros delegan). Si ambos están vacíos: 0 (no
# sabemos qué hacer — el caller decide).
validate_stage_store() {
    local stage_raw="${1:-}"
    local store_raw="${2:-}"

    case "$stage_raw" in
        production|prod|development|dev|"") ;;
        *) log_err "Stage inválido: ${stage_raw}. Válidos: production|prod, development|dev"
           return 1 ;;
    esac

    if [[ -n "$store_raw" ]]; then
        if [[ "$store_raw" == "all" ]]; then
            return 0
        fi
        if ! is_valid_store "$store_raw"; then
            log_err "Store inválido: ${store_raw}. Válidos: ${STORES[*]} o 'all'"
            return 1
        fi
    fi
    return 0
}

# _terminal_width
# Devuelve el ancho de la terminal en columnas. Usa 'tput cols' si
# esta disponible; fallback a 80 (default ANSI) si no. Limita a un
# minimo de 60 (para que las cajitas no se rompan en pantallas
# pequenas) y a un maximo de 200 (para que no sean demasiado
# largas en monitores ultra-wide).
_terminal_width() {
    local width
    if /usr/bin/command -v tput >/dev/null 2>&1; then
        width=$(/usr/bin/tput cols 2>/dev/null)
    fi
    [[ -z "$width" || "$width" -lt 60 ]] && width=80
    [[ "$width" -gt 200 ]] && width=200
    /usr/bin/printf '%d' "$width"
}

# _separator_line <ancho>
# Imprime '─' repetido <ancho> veces. Helper reutilizable.
_separator_line() {
    local width="${1:-70}"
    /usr/bin/printf '%*s' "$width" '' | /usr/bin/sed 's/ /─/g'
}

# print_section_title <fase> <numero> <total> <descripcion>
# Imprime una caja visual que delimita fases de un comando largo
# (ej setup). El humano distingue asi entre los 5-7 bloques
# diferentes que un setup imprime. Sin esto, el output se ve
# pegado y es imposible saber "ya termino X, ahora arranca Y".
#
# Layout (ancho completo de la terminal, indent de 2):
#   ┌── [1/5] Detectando hosts ─────────── ... ──────────┐
#
# El caracter unicode '┌┐└┘│─' se renderiza bien en terminales
# modernas. Si la terminal es muy vieja y no soporta unicode, el
# usuario vera '?' en su lugar — no es bloqueante.
print_section_title() {
    local fase="${1:-Step}"
    local num="${2:-1}"
    local total="${3:-1}"
    local desc="${4:-}"
    local prefix="[${num}/${total}] ${fase}"
    [[ -n "$desc" ]] && prefix="${prefix} — ${desc}"

    # Ancho total = columnas de terminal. La caja ocupa 2 chars
    # menos (los bordes ┌ y ┐). Padding interno de 2 + prefix.
    local width
    width=$(_terminal_width)
    local inner=$((width - 2))  # 2 bordes
    local content="── ${prefix} "
    local content_len=${#content}
    local pad=$((inner - content_len))
    [[ $pad -lt 0 ]] && pad=0
    local pad_str
    pad_str=$(_separator_line "$pad")

    printf "\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}┌%s%s┐${C_RESET}\n" "$content" "$pad_str"
}

# print_section_end
# Cierra la caja visual abierta por print_section_title. Simetrico.
# Ocupa todo el ancho de la terminal.
print_section_end() {
    local width
    width=$(_terminal_width)
    local inner=$((width - 2))
    local sep
    sep=$(_separator_line "$inner")
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}└%s┘${C_RESET}\n" "$sep"
}

show_target_summary() {
    local cmd_name="${1:-cmd}"
    local stage_raw="${2:-}"
    local store_raw="${3:-all}"
    local svc="${4:-}"

    # Normalizar stage para display (alias → nombre canónico).
    local stage_label
    case "$stage_raw" in
        production|prod) stage_label="production" ;;
        development|dev) stage_label="development" ;;
        "")              stage_label="?" ;;
        *)               stage_label="$stage_raw" ;;
    esac

    # Contar instancias objetivo (1..3, nunca más: dentro de un stage
    # siempre son 3 tiendas — panda-estampados-and-kitsune, mm-boutique, morales-store).
    local count
    if [[ "$store_raw" == "all" ]]; then
        count=$(printf '%s\n' "${ALL_INSTANCES[@]}" | /usr/bin/grep -c "^${stage_label}:" || true)
    else
        count=1
    fi

    local plural="instancias"
    [[ "$count" -eq 1 ]] && plural="instancia"

    local store_display="all"
    [[ "$store_raw" != "all" ]] && store_display="$store_raw"

    if [[ -n "$svc" ]]; then
        log_info "[${cmd_name}] ${C_BOLD}stage=${C_RESET}${stage_label} · ${C_BOLD}store=${C_RESET}${store_display} · ${C_BOLD}servicio=${C_RESET}${svc} · ${C_BOLD}${count}${C_RESET} ${plural}"
    else
        log_info "[${cmd_name}] ${C_BOLD}stage=${C_RESET}${stage_label} · ${C_BOLD}store=${C_RESET}${store_display} · ${C_BOLD}n=${C_RESET}${count} ${plural}"
    fi
}
