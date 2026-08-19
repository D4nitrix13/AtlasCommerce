#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/help/registry.sh
# ------------------------------------------------------------
# Registro centralizado de `cmd_help_<comando>` para todos los
# comandos del orquestador docker-stack. El dispatcher lo sourcea
# cuando el usuario pide help por subcomando:
#
#   $ ./scripts/docker-stack.sh volumes help
#   $ ./scripts/docker-stack.sh up dev help
#   $ ./scripts/docker-stack.sh rm-volumes -y all help
#
# Paleta (asignada por CATEGORÍA, no por línea):
#   Cinta header (━━━)       → C_BOLD + C_MAGENTA
#   Nombre del comando       → C_BOLD + C_BRIGHT_CYAN
#   Descripción del header   → C_DIM
#   Header de sección (▸ USO) → C_BOLD + C_BRIGHT_BLUE
#   Path del script ($0)     → C_BRIGHT_CYAN
#   Comando (volumes, up)    → C_BOLD + C_BRIGHT_MAGENTA
#   Args requeridos (<área>) → C_BOLD + C_BRIGHT_YELLOW
#   Args opcionales  [<x>]  → C_GREEN (más suaves)
#   Keyword / valor  (all)   → C_BOLD + C_BRIGHT_YELLOW + UL
#   Filas de tabla (all dev) → C_BOLD + C_BRIGHT_CYAN (keyword)
#                              + C_DIM (descripción después del →)
#   "PARA VER TODOS"          → C_BOLD + C_BRIGHT_RED (atención)
#   Comentarios tras #         → C_DIM
#   Sección ALIAS             → C_BOLD + C_BRIGHT_BLUE
# ------------------------------------------------------------

# Helper: header con cinta magenta y nombre del comando en bright cyan.
_registry_header() {
    printf "\n${C_BOLD}${C_MAGENTA}━━━${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%s${C_RESET} ${C_DIM}%s${C_RESET} ${C_BOLD}${C_MAGENTA}━━━${C_RESET}\n" \
        "$1" "$2"
}

# Header de sección con ancla `▸` en bright blue bold.
_registry_section() {
    printf "${C_BOLD}${C_BRIGHT_BLUE}▸ %s${C_RESET}\n" "$1"
}

# Bloque ÁREA estándar (default = all destacado en yellow underline).
_registry_area_block() {
    _registry_section "ÁREA"
    printf "  ${C_DIM}default ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}${C_DIM} → ver TODOS los targets${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                    "→ las 6 instancias (3 prod + 3 dev)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev (bind mounts + xdebug)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"                   "→ las 3 prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"
}

# Bloque INSTANCIA estándar (para comandos que requieren instancia,
# no área — ej: logs, exec).
_registry_instance_block() {
    _registry_section "INSTANCIA"
    printf "  ${C_DIM}obligatoria — no hay 'all' para este comando${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"
}

# Línea de USO estándar (path + comando + args opcionales).
# Uso: _registry_use "<cmd>" "[<args>]"
_registry_use() {
    _registry_section "USO"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} ${C_GREEN}%s${C_RESET}\n" \
        "$1" "$2"
}

# Línea de USO con argumento requerido en bright yellow bold.
# Uso: _registry_use_required "<cmd>" "<req_arg>" "[<opt>]"
_registry_use_required() {
    _registry_section "USO"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}%s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%s${C_RESET} ${C_GREEN}%s${C_RESET}\n" \
        "$1" "$2" "$3"
}

# ─────────────────────────────────────────────────────────────
# Lifecycle
# ─────────────────────────────────────────────────────────────

cmd_help_up() {
    _registry_header "up" "build + start containers"
    _registry_use "up" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA AFECTAR TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}                       ${C_DIM}# 6 instancias${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET}                                          ${C_DIM}# 6 (default = all)${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                                  ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}                      ${C_DIM}# 1 target (3 dev backend)${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BRIGHT_YELLOW}production:morales-store${C_RESET}        ${C_DIM}# 1 instancia prod${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Equivale a ${C_RESET}${C_BRIGHT_CYAN}docker compose up -d --build${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_start() {
    _registry_header "start" "levantar containers sin rebuild"
    _registry_use "start" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA LEVANTAR TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET}                                        ${C_DIM}# 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} ${C_BRIGHT_YELLOW}prod${C_RESET}                                ${C_DIM}# 3 prod${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}start${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}mailpit${C_RESET}                   ${C_DIM}# 1 target${C_RESET}\n"

    printf "\n"
    _registry_section "DIFERENCIA CON up"
    printf "  ${C_DIM}start =${C_RESET} ${C_BRIGHT_CYAN}docker compose start${C_RESET}${C_DIM} (no rebuild). up = build + start.${C_RESET}\n"
}

cmd_help_stop() {
    _registry_header "stop" "detener containers (preserva volúmenes)"
    _registry_use "stop" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA DETENER TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET}                             ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} ${C_BRIGHT_YELLOW}prod${C_RESET}                         ${C_DIM}# las 3 prod${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}stop${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}             ${C_DIM}# solo backend de las 3 dev${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Volúmenes nombrados NO se borran. Usar ${C_RESET}${C_BRIGHT_CYAN}rm-volumes${C_RESET}${C_DIM} para eso.${C_RESET}\n"
}

cmd_help_restart() {
    _registry_header "restart" "reiniciar containers"
    _registry_use "restart" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA REINICIAR TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET}                                      ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                              ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}              ${C_DIM}# 1 target${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BRIGHT_YELLOW}production:panda-estampados${C_RESET} ${C_DIM}# 1 instancia${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}rs${C_RESET}\n"
}

cmd_help_build() {
    _registry_header "build" "rebuild imágenes (--no-cache)"
    _registry_use "build" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA REBUILD TODO${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET}                                                ${C_DIM}# 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                                    ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}                    ${C_DIM}# 1 target${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BRIGHT_YELLOW}production:panda-estampados${C_RESET} ${C_GREEN}frontend${C_RESET} ${C_DIM}# 1 instancia${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}b${C_RESET}\n"
    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Para limpiar el cache de Docker antes: ${C_RESET}${C_BRIGHT_CYAN}docker builder prune${C_RESET}\n"
}

cmd_help_down() {
    _registry_header "down" "detener y eliminar containers (preserva volúmenes)"
    _registry_use "down" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA BAJAR TODO${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET}                             ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                         ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}down${C_RESET} ${C_BRIGHT_YELLOW}production${C_RESET}                  ${C_DIM}# las 3 prod${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Volúmenes nombrados (postgres_data, storage, etc.) NO se borran.${C_RESET}\n"
    printf "  ${C_DIM}Para eso, usar ${C_RESET}${C_BRIGHT_CYAN}rm-volumes${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_nuke() {
    _registry_header "nuke" "down + eliminar volúmenes (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}⚠ DESTRUCTIVO${C_RESET}\n"
    _registry_use "nuke" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}nuke${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                       ${C_BRIGHT_RED}# ⚠ borra TODOS los volúmenes de las 3 dev${C_RESET}\n"

    printf "\n"
    _registry_section "QUÉ BORRA"
    printf "  ${C_DIM}·${C_RESET} bases Postgres (todos los tenants)\n"
    printf "  ${C_DIM}·${C_RESET} storage de Laravel\n"
    printf "  ${C_DIM}·${C_RESET} node_modules de pnpm\n"
    printf "  ${C_DIM}·${C_RESET} caches de Astro / Vite\n"

    printf "\n"
    _registry_section "DESPUÉS DE NUKE"
    printf "  ${C_DIM}Hay que correr ${C_RESET}${C_BRIGHT_CYAN}setup dev${C_RESET}${C_DIM} para recrear las 6 instancias.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Inspección
# ─────────────────────────────────────────────────────────────

cmd_help_status() {
    _registry_header "status" "compose ps de cada instancia"
    _registry_use "status" "[<área>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER ESTADO DE TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET}                           ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}status${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                   ${C_DIM}# las 3 dev${C_RESET}\n"

    printf "\n"
    _registry_section "SALIDA"
    printf "  ${C_DIM}Lista service, state, ports por container.${C_RESET}\n"
    printf "  ${C_DIM}Para vista detallada (image, command, networks) usar ${C_RESET}${C_BRIGHT_CYAN}info${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_ps() {
    _registry_header "ps" "lista rápida de containers (vista docker ps)"
    _registry_use "ps" "[<área>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER TODOS LOS CONTAINERS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}ps${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}ps${C_RESET}                  ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}ps${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}             ${C_DIM}# las 3 dev${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}ls${C_RESET}\n"
    printf "\n"
    _registry_section "DIFERENCIA CON status"
    printf "  ${C_DIM}ps = vista rápida de ${C_RESET}${C_BRIGHT_CYAN}docker ps${C_RESET}${C_DIM}. status = compose ps.${C_RESET}\n"
    printf "  ${C_DIM}Para detalles por container, ${C_RESET}${C_BRIGHT_CYAN}info${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_info() {
    _registry_header "info" "tabla detallada: instancia · servicio · estado · puertos"
    _registry_use "info" "[<área>] [<servicio>]"
    _registry_area_block

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER INFO DE TODOS${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET}                                         ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                                 ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}info${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}nginx${C_RESET}                  ${C_DIM}# nginx de las 3 dev${C_RESET}\n"

    printf "\n"
    _registry_section "ALIAS"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}psv${C_RESET}\n"
}

cmd_help_logs() {
    _registry_header "logs" "tail de logs de containers"
    _registry_use_required "logs" "<instancia>" "[<servicio>]"
    _registry_instance_block

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development:panda-estampados${C_RESET}              ${C_DIM}# 1 instancia${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}                           ${C_DIM}# 3 dev, backend${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production:morales-store${C_RESET} ${C_GREEN}app${C_RESET}     ${C_DIM}# 1 instancia + servicio${C_RESET}\n"

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
    _registry_use_required "volumes" "<área>" "[<servicio>]"

    printf "\n"
    printf "  ${C_DIM}<área> REQUERIDA — no hay default. Pasar 'all' para ver todos.${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                    "→ las 6 instancias (3 prod + 3 dev)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev (bind mounts + xdebug)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"                   "→ las 3 prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA VER TODOS${C_RESET} ${C_BRIGHT_RED}(la única forma es pasar 'all')${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}                              ${C_DIM}# 6 instancias${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                                  ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}                  ${C_DIM}# backend de las 3 dev${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BRIGHT_YELLOW}production${C_RESET}                       ${C_DIM}# 3 prod${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}volumes${C_RESET} ${C_BRIGHT_YELLOW}production:morales-store${C_RESET}         ${C_DIM}# 1 instancia prod${C_RESET}\n"

    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 volumes${C_RESET} ${C_BRIGHT_RED}# error: <área> requerido${C_RESET}\n"
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
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_GREEN}[-y]${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<área>${C_RESET} ${C_GREEN}[<servicio>]${C_RESET}\n"

    printf "\n"
    _registry_section "FLAGS"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}-y${C_RESET}    ${C_DIM}saltear confirmación interactiva (cuidado)${C_RESET}\n"

    printf "\n"
    printf "  ${C_DIM}<área> REQUERIDA — no hay default. Pasar 'all' para borrar todos.${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                    "→ las 6 instancias"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"                   "→ las 3 prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"

    printf "\n"
    printf "${C_BOLD}${C_BRIGHT_RED}▸ PARA BORRAR TODO${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_GREEN}-y${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}                              ${C_DIM}# pedir confirmación${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_GREEN}-y${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET} ${C_GREEN}backend${C_RESET}    ${C_DIM}# sin confirmación, 1 target${C_RESET}\n"

    printf "\n"
    _registry_section "ERROR SI FALTA"
    printf "  ${C_DIM}\$0 rm-volumes${C_RESET}      ${C_BRIGHT_RED}# error: <área> requerido${C_RESET}\n"
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
    _registry_use_required "exec" "<instancia> <servicio>" "<comando...>"
    _registry_instance_block

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development:mm-boutique app bash${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BRIGHT_YELLOW}backend${C_RESET} ${C_BRIGHT_GREEN}php artisan migrate --seed${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production:panda-estampados app ls -la /var/www/html${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Si el servicio tiene varios containers (nginx + php-fpm),${C_RESET}\n"
    printf "  ${C_DIM}toma el principal. Para uno específico:${C_RESET}\n"
    printf "    ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<inst> <svc>${C_RESET} ${C_GREEN}--index N <comando>${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Setup
# ─────────────────────────────────────────────────────────────

cmd_help_setup() {
    _registry_header "setup" "primera instalación: copiar .env y levantar"
    _registry_use "setup" "[<área>]"

    printf "\n"
    _registry_section "ÁREA"
    printf "  ${C_DIM}default ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}${C_DIM} → preparar las 6${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"        "→ las 6 instancias"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"        "→ las 3 dev"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"       "→ las 3 prod"

    printf "\n"
    _registry_section "QUÉ HACE"
    printf "  ${C_DIM}1.${C_RESET} copia ${C_BRIGHT_CYAN}docker/instances/<stage>/<store>/environment/.env.example${C_RESET}${C_DIM} a .env${C_RESET}\n"
    printf "  ${C_DIM}2.${C_RESET} construye las imágenes\n"
    printf "  ${C_DIM}3.${C_RESET} levanta los containers\n"
    printf "  ${C_DIM}4.${C_RESET} corre migraciones y seeders base\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET}              ${C_DIM}# preparar las 6 (default = all)${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BOLD}${C_UL}${C_BRIGHT_YELLOW}all${C_RESET}        ${C_DIM}# explícito${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BRIGHT_YELLOW}dev${C_RESET}        ${C_DIM}# preparar las 3 dev${C_RESET}\n"
}

cmd_help_hosts() {
    _registry_header "hosts" "imprime el bloque para /etc/hosts"
    printf "\n"
    _registry_section "USO"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}hosts${C_RESET}\n"

    printf "\n"
    _registry_section "EJEMPLOS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}hosts${C_RESET}                            ${C_DIM}# imprime líneas${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}sudo ./scripts/setup-local.sh${C_RESET}                  ${C_DIM}# automágico${C_RESET}\n"

    printf "\n"
    _registry_section "NOTA"
    printf "  ${C_DIM}Lista de IPs internas que Caddy del host necesita para resolver.${C_RESET}\n"
}

cmd_help_caddy() {
    _registry_header "caddy" "imprime el bloque Caddyfile"
    printf "\n"
    _registry_section "USO"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}caddy${C_RESET}\n"

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
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                                    ${C_DIM}# global${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_GREEN}<comando>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                  ${C_DIM}# per-comando${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_GREEN}<comando>${C_RESET} ${C_BRIGHT_YELLOW}<área>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET} ${C_DIM}# per-comando + área${C_RESET}\n"

    printf "\n"
    _registry_section "ATAJOS"
    printf "  ${C_BRIGHT_YELLOW}-h${C_RESET}, ${C_BRIGHT_YELLOW}--help${C_RESET} en cualquier posición se tratan como ${C_BOLD}${C_BRIGHT_CYAN}help${C_RESET}.\n"

    printf "\n"
    _registry_section "DESCUBRIR MÁS"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}                  ${C_DIM}# este mensaje${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}$0${C_RESET} ${C_GREEN}<comando>${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}help${C_RESET}           ${C_DIM}# detalles del comando${C_RESET}\n"
}
