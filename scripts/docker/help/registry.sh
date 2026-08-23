#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/help/registry.sh
# ------------------------------------------------------------
# Registro centralizado de `cmd_help_<comando>` para todos los
# comandos del orquestador docker-stack. El dispatcher lo sourcea
# cuando el usuario pide help por subcomando:
#
#   $ ./scripts/docker-stack.sh up help
#   $ ./scripts/docker-stack.sh build dev all help
#   $ ./scripts/docker-stack.sh rm-volumes -y dev all help
#
# Paleta (asignada por CATEGORÍA, no por línea) — diferenciada para
# que stage / store / service / flag sean visualmente distinguibles:
#   Cinta header (━━━)        → C_BOLD + C_MAGENTA
#   Nombre del comando        → C_BOLD + C_BRIGHT_CYAN
#   Descripción del header    → C_DIM
#   Header de sección (▸ USO) → C_BOLD + C_BRIGHT_BLUE
#   Path del script ($0)      → C_CYAN (más suave que bright)
#   Comando (build, up, …)    → C_BOLD + C_BRIGHT_MAGENTA
#   Stage (production, dev)   → C_BOLD + C_BRIGHT_YELLOW
#   Store (panda-estampados)  → C_BOLD + C_BRIGHT_CYAN
#   Service (backend)         → C_BOLD + C_BRIGHT_GREEN
#   Flag (no-cache, -y)       → C_BOLD + C_BRIGHT_RED (atención)
#   "PARA AFECTAR TODOS"      → C_BOLD + C_BRIGHT_RED
#   Comentarios tras #        → C_DIM
#   Sección ALIAS             → C_BOLD + C_BRIGHT_BLUE
#
# Comentarios en ejemplos se alinean vía _registry_example (calcula
# padding dinámicamente, no manual).
# ------------------------------------------------------------

# Ancho de columna donde arranca el comentario `# ...` en ejemplos.
# Medido en caracteres VISIBLES (sin contar códigos ANSI). Calibrado
# para que el ejemplo más largo del orquestador quepa:
#   "./scripts/docker-stack.sh build no-cache dev panda-estampados backend"
#   = 71 chars visibles. Con 78 hay 7 chars de padding mínimo.
_REGISTRY_EXAMPLE_COMMENT_COL=78

# Helper: header con cinta magenta y nombre del comando en bright cyan.
_registry_header() {
    printf "\n${C_BOLD}${C_MAGENTA}━━━${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%s${C_RESET} ${C_DIM}%s${C_RESET} ${C_BOLD}${C_MAGENTA}━━━${C_RESET}\n" \
        "$1" "$2"
}

# Header de sección con ancla `▸` en bright blue bold.
_registry_section() {
    printf "${C_BOLD}${C_BRIGHT_BLUE}▸ %s${C_RESET}\n" "$1"
}

# Bloque STAGE estándar. Stages en yellow para diferenciarlos de stores.
_registry_stage_block() {
    _registry_section "STAGE"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production | prod"     "→ instancias de producción"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development | dev"     "→ instancias de development"
}

# Bloque STORE estándar. Stores en cyan.
_registry_store_block() {
    _registry_section "STORE"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "panda-estampados"      "→ 1 instancia (multi-tenant: Pandas + Kitsune)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "mm-boutique"           "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "morales-store"         "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                   "→ todas las tiendas del stage"
}

# Bloque STAGE+STORE combinado (atajo para comandos que usan ambos).
_registry_stage_store_block() {
    _registry_stage_block
    printf "\n"
    _registry_store_block
}

# Línea de USO estándar (path + comando + args opcionales).
# Uso: _registry_use "<cmd>" "[<args>]"
_registry_use() {
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} ${C_BRIGHT_GREEN}%s${C_RESET}\n" \
        "$1" "$2"
}

# Línea de USO con argumento requerido (stage) en yellow.
# Uso: _registry_use_required "<cmd>" "<req_arg>" "[<opt>]"
_registry_use_required() {
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%s${C_RESET} ${C_BRIGHT_GREEN}%s${C_RESET}\n" \
        "$1" "$2" "$3"
}

# Línea de USO con stage + store (yellow + cyan) + servicio opcional (green).
# Uso: _registry_use_required2 "<cmd>" "<req_stage>" "<req_store>" "[<opt_svc>]"
_registry_use_required2() {
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%s${C_RESET} ${C_BRIGHT_GREEN}%s${C_RESET}\n" \
        "$1" "$2" "$3" "$4"
}

# Línea de USO con flag opcional (red bold) + stage (yellow) + store (cyan) + svc opcional (green).
# Uso: _registry_use_required2_flag "<cmd>" "<flag_name>" "<req_stage>" "<req_store>" "[<opt_svc>]"
# El flag se imprime como `[name]` con corchetes en green dim y el name en red bold —
# destaque de "esto puede romper cosas" sin perder la convención de corchetes = opcional.
_registry_use_required2_flag() {
    _registry_section "USO"
    local flag_name="$2"
    local flag_part=""
    if [[ -n "$flag_name" ]]; then
        flag_part="${C_BRIGHT_GREEN}[${C_BOLD}${C_BRIGHT_RED}${flag_name}${C_BRIGHT_GREEN}]${C_RESET} "
    fi
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} %s${C_BOLD}${C_BRIGHT_YELLOW}%s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%s${C_RESET} ${C_BRIGHT_GREEN}%s${C_RESET}\n" \
        "$1" "$flag_part" "$3" "$4" "$5"
}

# Helper de EJEMPLO con alineación de comentario.
#
# Uso: _registry_example "<ruta-del-script>" "<ansi-styled-cmd-and-args>" "<comment>"
#
# Imprime una línea de ejemplo con el comentario alineado en la columna
# `_REGISTRY_EXAMPLE_COMMENT_COL`. Padding calculado dinámicamente
# contando los caracteres VISIBLES (sin códigos ANSI) del ejemplo, no
# del string crudo — eso permite usar colores ANSI inline sin romper
# la alineación.
#
# Ejemplo:
#   _registry_example "$0" \
#       "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
#       "sin cache, 1 target"
#
# Resultado (los `<X>` son códigos ANSI expandidos):
#   ./scripts/docker-stack.sh <X>build<X> <X>no-cache<X> <X>dev<X> <X>panda-estampados<X> <X>backend<X>    # sin cache, 1 target
#                                                  ^ el `#` cae en col 78 visible
_registry_example() {
    local script_path="$1"
    local styled="$2"
    local comment="$3"

    # Construir el ejemplo completo: "<path> <styled>".
    local full="${C_CYAN}${script_path}${C_RESET} ${styled}"

    # Calcular longitud visible (sin códigos ANSI). Usamos sed para
    # remover los escapes \x1b\[...m antes de contar con wc -c.
    local visible
    visible=$(printf '%s' "$full" | /usr/bin/sed -E 's/\x1b\[[0-9;]*[a-zA-Z]//g' | /usr/bin/wc -c | /usr/bin/tr -d ' ')

    local pad=$((_REGISTRY_EXAMPLE_COMMENT_COL - visible))
    # Si el ejemplo es más largo que la columna, dar 2 chars mínimo
    # para que el `#` no quede pegado.
    [[ $pad -lt 2 ]] && pad=2

    # Imprimir con padding calculado. Usamos %${pad}s con string vacío
    # para producir exactamente N espacios.
    printf "  %s%${pad}s${C_DIM}# %s${C_RESET}\n" "$full" "" "$comment"
}

# Helper que envuelve la keyword recomendada con underline. Usado en
# secciones "PARA REBUILD TODO" etc. Stage en yellow underlined + store
# en cyan underlined, separados por espacio.
_registry_recommended() {
    local stage="$1"
    local store="$2"
    printf "${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}%s${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_CYAN}%s${C_RESET}" \
        "$stage" "$store"
}

# ─────────────────────────────────────────────────────────────
# Lifecycle
# ─────────────────────────────────────────────────────────────

cmd_help_up() {
    _registry_header "up" "build + start containers"
    _registry_use_required2 "up" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA AFECTAR TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} $(_registry_recommended production all)" \
        "3 prod"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} $(_registry_recommended dev all)" \
        "3 dev"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 instancia dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "3 dev, solo backend"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET}" \
        "1 instancia prod"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Equivale a ${C_RESET}${C_CYAN}docker compose up -d --build${C_RESET}${C_DIM}.${C_RESET}\n"
    printf "  ${C_BRIGHT_RED}${C_BOLD}▸${C_RESET} ${C_BOLD}Si cambiaste environment/.env${C_RESET}${C_DIM}, usá este comando (no restart) — up${C_RESET}\n"
    printf "    ${C_DIM}detecta cambios en env_file y recrea los containers para recargar las variables.${C_RESET}\n"
}

cmd_help_start() {
    _registry_header "start" "levantar containers sin rebuild"
    _registry_use_required2 "start" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA LEVANTAR TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} $(_registry_recommended production all)" \
        ""
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}prod${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "3 prod"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}mailpit${C_RESET}" \
        "3 dev, solo mailpit"

    printf "\n"
    _registry_section "DIFERENCIA CON up"
    printf "  ${C_DIM}start =${C_RESET} ${C_CYAN}docker compose start${C_RESET}${C_DIM} (no rebuild). up = build + start.${C_RESET}\n"
}

cmd_help_stop() {
    _registry_header "stop" "detener containers (preserva volúmenes)"
    _registry_use_required2 "stop" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA DETENER TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} $(_registry_recommended production all)" \
        ""
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "solo backend de las 3 dev"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Volúmenes nombrados NO se borran. Usar ${C_RESET}${C_CYAN}rm-volumes${C_RESET}${C_DIM} para eso.${C_RESET}\n"
}

cmd_help_restart() {
    _registry_header "restart" "reiniciar containers"
    _registry_use_required2 "restart" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA REINICIAR TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "1 instancia + servicio"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}rs${C_RESET}\n"

    printf "\n"
    _registry_section "⚠ CAMBIASTE EL .env? USÁ up, NO restart"
    printf "  ${C_DIM}restart${C_RESET} ${C_DIM}solo reinicia el proceso.${C_RESET}\n"
    printf "  ${C_DIM}NO recarga variables de environment/.env — esas se leen${C_RESET}\n"
    printf "  ${C_DIM}una sola vez al crear el container. Para que tome cambios:${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store>${C_RESET} ${C_BRIGHT_GREEN}[<svc>]${C_RESET}\n"
    printf "  ${C_DIM}(docker compose detecta el cambio en env_file y recrea solo los containers afectados)${C_RESET}\n"
}

cmd_help_build() {
    _registry_header "build" "rebuild imágenes (con cache por default)"
    _registry_use_required2_flag "build" "no-cache" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "  ${C_DIM}<stage> y <store> REQUERIDOS — no hay default. Pasar 'all' en store para rebuild todo del stage.${C_RESET}\n"

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA REBUILD TODO${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} $(_registry_recommended production all)" \
        "3 prod (con cache)"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} $(_registry_recommended dev all)" \
        "3 dev"

    printf "\n"
    _registry_section "FLAGS"
    printf "  ${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET}  ${C_DIM}rebuild completo sin usar cache de layers (lento).${C_RESET}\n"
    printf "              ${C_DIM}Acepta el flag en cualquier posición.${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 instancia dev (con cache)"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "3 dev backend (con cache)"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "sin cache, 1 target"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}frontend${C_RESET}" \
        "1 instancia prod"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}b${C_RESET}\n"
    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 build${C_RESET}                  ${C_BRIGHT_RED}# error: <stage> y <store> requeridos${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Por default USA el cache de layers (rápido).${C_RESET}\n"
    printf "  ${C_DIM}Pasar ${C_RESET}${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET}${C_DIM} solo cuando:${C_RESET}\n"
    printf "  ${C_DIM}·${C_RESET} cambió algo en una imagen base no detectable por checksum${C_RESET}\n"
    printf "  ${C_DIM}·${C_RESET} el cache quedó stale y querés forzar rebuild${C_RESET}\n"
    printf "  ${C_DIM}Para limpiar el cache global de Docker: ${C_RESET}${C_CYAN}docker builder prune${C_RESET}\n"
}

cmd_help_down() {
    _registry_header "down" "detener y eliminar containers (preserva volúmenes)"
    _registry_use_required2 "down" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA BAJAR TODO${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} $(_registry_recommended production all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET}" \
        "1 instancia prod"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Volúmenes nombrados (postgres_data, storage, etc.) NO se borran.${C_RESET}\n"
    printf "  ${C_DIM}Para eso, usar ${C_RESET}${C_CYAN}rm-volumes${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_nuke() {
    _registry_header "nuke" "down + eliminar volúmenes (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}⚠ DESTRUCTIVO${C_RESET}\n"
    _registry_use_required2 "nuke" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}nuke${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "⚠ borra TODOS los volúmenes de las 3 dev"

    printf "\n"
    _registry_section "QUÉ BORRA"
    printf "  ${C_DIM}·${C_RESET} bases Postgres (todos los tenants)\n"
    printf "  ${C_DIM}·${C_RESET} storage de Laravel\n"
    printf "  ${C_DIM}·${C_RESET} node_modules de pnpm\n"
    printf "  ${C_DIM}·${C_RESET} caches de Astro / Vite\n"

    printf "\n"
    _registry_section "DESPUÉS DE NUKE"
    printf "  ${C_DIM}Hay que correr ${C_RESET}${C_CYAN}setup dev all${C_RESET}${C_DIM} para recrear las 3 dev.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Inspección
# ─────────────────────────────────────────────────────────────

cmd_help_status() {
    _registry_header "status" "compose ps de cada instancia"
    _registry_use_required2 "status" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER ESTADO DE TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET} $(_registry_recommended production all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 instancia"

    printf "\n"
    _registry_section "SALIDA"
    printf "  ${C_DIM}Lista service, state, ports por container.${C_RESET}\n"
    printf "  ${C_DIM}Para vista detallada (image, command, networks) usar ${C_RESET}${C_CYAN}info${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_ps() {
    _registry_header "ps" "lista rápida de containers (vista docker ps)"
    _registry_use "ps" ""
    printf "\n"
    printf "  ${C_DIM}ps = vista rápida de TODAS las instancias en una sola tabla.${C_RESET}\n"
    printf "  ${C_DIM}Para filtrar por stage/store/servicio usar ${C_RESET}${C_CYAN}status${C_RESET}${C_DIM}.${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}ls${C_RESET}\n"
    printf "\n"
    _registry_section "DIFERENCIA CON status"
    printf "  ${C_DIM}ps = vista rápida de ${C_RESET}${C_CYAN}docker ps${C_RESET}${C_DIM}. status = compose ps filtrado.${C_RESET}\n"
}

cmd_help_info() {
    _registry_header "info" "tabla detallada: instancia · servicio · estado · puertos"
    _registry_use_required2 "info" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER INFO DE TODOS${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}nginx${C_RESET}" \
        "nginx de 1 instancia"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}psv${C_RESET}\n"
}

cmd_help_logs() {
    _registry_header "logs" "tail de logs de containers"
    _registry_use_required2 "logs" "<stage>" "<store|all>" "<servicio>"
    _registry_stage_store_block

    printf "\n"
    printf "  ${C_DIM}<servicio> REQUERIDO — el dispatcher rechaza logs sin servicio.${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "1 instancia, 1 svc"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "3 dev backend en paralelo"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}nginx${C_RESET}" \
        "1 prod"

    printf "\n"
    _registry_section "ATAJOS"
    printf "  ${C_DIM}Ctrl-C sale del tail.${C_RESET}\n"
    printf "  ${C_DIM}Para flags de compose (--since, --tail), pasarlos al final.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Volumes
# ─────────────────────────────────────────────────────────────

cmd_help_volumes() {
    _registry_header "volumes" "listar volúmenes Docker declarados por las instancias"
    _registry_use_required2 "volumes" "<stage>" "<store|all>" "[<servicio>]"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER TODOS DEL STAGE${C_RESET} ${C_BRIGHT_RED}(pasar 'all' en store)${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "3 dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 instancia dev"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "backend de las 3 prod"

    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 volumes${C_RESET} ${C_BRIGHT_RED}# error: <stage> y <store> requeridos${C_RESET}\n"
    printf "  ${C_DIM}\$0 volumes help${C_RESET} ${C_BRIGHT_RED}# este help${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}vol${C_RESET}\n"
}

cmd_help_rm_volumes() {
    _registry_header "rm-volumes" "eliminar volúmenes (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}⚠ DESTRUCTIVO${C_RESET}\n"

    _registry_section "USO"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BRIGHT_GREEN}[${C_BOLD}${C_BRIGHT_RED}-y${C_RESET}${C_BRIGHT_GREEN}]${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store|all>${C_RESET} ${C_BRIGHT_GREEN}[<servicio>]${C_RESET}\n"

    printf "\n"
    _registry_section "FLAGS"
    printf "  ${C_BOLD}${C_BRIGHT_RED}-y${C_RESET}    ${C_DIM}saltear confirmación interactiva (cuidado)${C_RESET}\n"

    printf "\n"
    _registry_stage_store_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA BORRAR TODO DEL STAGE${C_RESET}\n"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BOLD}${C_BRIGHT_RED}-y${C_RESET} $(_registry_recommended dev all)" \
        ""

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "pedir confirmación"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BOLD}${C_BRIGHT_RED}-y${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}" \
        "sin confirmación, 3 dev backend"

    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 rm-volumes${C_RESET}      ${C_BRIGHT_RED}# error: <stage> y <store> requeridos${C_RESET}\n"
    printf "  ${C_DIM}\$0 rm-volumes -y${C_RESET}   ${C_BRIGHT_RED}# error también${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}rmvol${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Exec
# ─────────────────────────────────────────────────────────────

cmd_help_exec() {
    _registry_header "exec" "ejecutar comando dentro de un container"
    printf "\n"
    _registry_section "USO"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store>${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}<servicio>${C_RESET} ${C_BRIGHT_GREEN}<comando...>${C_RESET}\n"

    printf "\n"
    _registry_stage_block

    printf "\n"
    printf "  ${C_DIM}<servicio> y <comando...> son REQUERIDOS (>=1 token después del servicio).${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET} ${C_BRIGHT_GREEN}bash${C_RESET}" \
        ""
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET} ${C_BRIGHT_GREEN}php artisan migrate --seed${C_RESET}" \
        ""
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}morales-store${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}app${C_RESET} ${C_BRIGHT_GREEN}ls -la /var/www/html${C_RESET}" \
        ""

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Si el servicio tiene varios containers (nginx + php-fpm),${C_RESET}\n"
    printf "  ${C_DIM}toma el principal. Para uno específico:${C_RESET}\n"
    printf "    ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store>${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}<svc>${C_RESET} ${C_BRIGHT_GREEN}--index N <comando>${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Setup
# ─────────────────────────────────────────────────────────────

cmd_help_setup() {
    _registry_header "setup" "primera instalación: copiar .env y levantar"
    _registry_use_required2 "setup" "<stage>" "<store|all>" ""
    _registry_stage_store_block

    printf "\n"
    printf "  ${C_DIM}Si <store> es 'all', aplica a todas las tiendas del stage.${C_RESET}\n"

    printf "\n"
    _registry_section "QUÉ HACE"
    printf "  ${C_DIM}1.${C_RESET} copia ${C_CYAN}docker/instances/<stage>/<store>/environment/.env.example${C_RESET}${C_DIM} a .env${C_RESET}\n"
    printf "  ${C_DIM}2.${C_RESET} construye las imágenes\n"
    printf "  ${C_DIM}3.${C_RESET} levanta los containers\n"
    printf "  ${C_DIM}4.${C_RESET} corre migraciones y seeders base\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}" \
        "las 3 prod"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 instancia dev"
}

cmd_help_fresh() {
    _registry_header "fresh" "fresh start de 1 instancia (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}⚠ DESTRUCTIVO${C_RESET} ${C_DIM}— borra volumes + rebuild + reinit + migrate + seed${C_RESET}\n"
    _registry_use_required2 "fresh" "<stage>" "<store>" ""

    printf "\n"
    printf "  ${C_BRIGHT_RED}<store> es OBLIGATORIO y NO acepta 'all'${C_RESET}${C_DIM} (debe ser 1 tienda específica).${C_RESET}\n"

    printf "\n"
    _registry_section "QUÉ HACE"
    printf "  ${C_DIM}1.${C_RESET} docker compose down -v (borra containers + volúmenes)\n"
    printf "  ${C_DIM}2.${C_RESET} up -d --build (rebuild imágenes + levantar)\n"
    printf "  ${C_DIM}3.${C_RESET} pre-initdb workaround para postgis (manual initdb)\n"
    printf "  ${C_DIM}4.${C_RESET} createdb (crea la DB del tenant)\n"
    printf "  ${C_DIM}5.${C_RESET} migrate + db:seed (esquema + datos base)\n"
    printf "  ${C_DIM}6.${C_RESET} tenants:bootstrap (seed específico de la instancia)\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}fresh${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}" \
        "1 dev fresh"
    _registry_example "$0" \
        "${C_BOLD}${C_BRIGHT_MAGENTA}fresh${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET}" \
        "1 prod fresh"

    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 fresh dev all${C_RESET}         ${C_BRIGHT_RED}# error: 'all' no válido para fresh${C_RESET}\n"
}

cmd_help_hosts() {
    _registry_header "hosts" "imprime el bloque para /etc/hosts"
    printf "\n"
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}hosts${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}hosts${C_RESET}                            ${C_DIM}# imprime líneas${C_RESET}\n"
    printf "  ${C_CYAN}sudo ./scripts/setup-local.sh${C_RESET}                  ${C_DIM}# automágico${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Lista de IPs internas que Caddy del host necesita para resolver.${C_RESET}\n"
}

cmd_help_caddy() {
    _registry_header "caddy" "imprime el bloque Caddyfile"
    printf "\n"
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}caddy${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Bloque Caddy (puerto 80/443) para los dominios de las instancias.${C_RESET}\n"
    printf "  ${C_BRIGHT_RED}${C_DIM}Si usás docker-compose de Caddy, IGNORAR.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Help
# ─────────────────────────────────────────────────────────────

cmd_help_help() {
    _registry_header "help" "mostrar ayuda"

    printf "\n"
    _registry_section "USO"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                                    ${C_DIM}# global${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BRIGHT_GREEN}<comando>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                  ${C_DIM}# per-comando${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BRIGHT_GREEN}<comando>${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET} ${C_DIM}# per-comando + args${C_RESET}\n"

    printf "\n"
    _registry_section "ATAJOS"
    printf "  ${C_BRIGHT_YELLOW}-h${C_RESET}, ${C_BRIGHT_YELLOW}--help${C_RESET} en cualquier posición se tratan como ${C_BOLD}${C_BRIGHT_CYAN}help${C_RESET}.\n"

    printf "\n"
    _registry_section "DESCUBRIR MÁS"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                  ${C_DIM}# este mensaje${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BRIGHT_GREEN}<comando>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}           ${C_DIM}# detalles del comando${C_RESET}\n"
}
