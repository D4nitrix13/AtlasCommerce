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
# Convención de la función:
#   - Sin argumentos (ignora el área porque el help es del comando,
#     no del scope).
#   - Salida por stdout via printf (no heredoc — para que $0,
#     C_*, etc. expandan).
#   - Líneas ≤ 80 chars (regla del proyecto).
#   - Paleta de colores: C_BOLD, C_CYAN, C_YELLOW, C_GREEN,
#     C_RED, C_MAGENTA, C_DIM, C_RESET (de colors.sh).
#
# Estructura típica de cada bloque:
#   1. header (cinta ─── con nombre del comando en C_CYAN bold)
#   2. USO (cómo se invoca, placeholders coloreados)
#   3. ÁREA (default all destacado en amarillo)
#   4. UNA LÍNEA para "para ver todos, usar 'all'"
#   5. EJEMPLOS (con ${C_CYAN}$0 ...${C_RESET})
#   6. ALIAS (si tiene)
# ------------------------------------------------------------

# Helper para imprimir el header colorado de cada help.
_registry_header() {
    printf "\n${C_BOLD}${C_MAGENTA}━━━${C_RESET} ${C_BOLD}${C_CYAN}%s${C_RESET} ${C_DIM}%s${C_RESET} ${C_BOLD}${C_MAGENTA}━━━${C_RESET}\n" \
        "$1" "$2"
}

# Helper para la sección de ÁREA estándar (compartida por lifecycle,
# inspección, volumes). Default siempre all.
_registry_area_block() {
    printf "${C_BOLD}ÁREA${C_RESET}  ${C_DIM}(default ${C_BOLD}${C_YELLOW}all${C_RESET}${C_DIM} → ver TODOS los targets)${C_RESET}\n"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                    "→ las 6 instancias (3 prod + 3 dev)"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev (bind mounts + xdebug)"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"                   "→ las 3 prod"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"
}

# ─────────────────────────────────────────────────────────────
# Lifecycle
# ─────────────────────────────────────────────────────────────

cmd_help_up() {
    _registry_header "up" "build + start containers"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}up${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA AFECTAR TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 up${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}                       ${C_DIM}# 6 instancias${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 up${C_RESET}                                ${C_DIM}# 6 (default = all)${C_RESET}\n"
    printf "  ${C_CYAN}$0 up dev${C_RESET}                            ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 up dev backend${C_RESET}                    ${C_DIM}# 1 target (3 dev backend)${C_RESET}\n"
    printf "  ${C_CYAN}$0 up production:morales-store${C_RESET}      ${C_DIM}# 1 instancia prod${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Equivale a ${C_RESET}${C_CYAN}docker compose up -d --build${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_start() {
    _registry_header "start" "levantar containers sin rebuild"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}start${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA LEVANTAR TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 start${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 start${C_RESET}                              ${C_DIM}# 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 start prod${C_RESET}                          ${C_DIM}# 3 prod${C_RESET}\n"
    printf "  ${C_CYAN}$0 start dev mailpit${C_RESET}                   ${C_DIM}# 1 target (mailpit de las 3 dev)${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}DIFERENCIA CON up${C_RESET}\n"
    printf "  ${C_DIM}start =${C_RESET} ${C_CYAN}docker compose start${C_RESET}${C_DIM} (no rebuild). up = build + start.${C_RESET}\n"
}

cmd_help_stop() {
    _registry_header "stop" "detener containers (preserva volúmenes)"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}stop${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA DETENER TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 stop${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 stop${C_RESET}                       ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 stop prod${C_RESET}                   ${C_DIM}# las 3 prod${C_RESET}\n"
    printf "  ${C_CYAN}$0 stop dev backend${C_RESET}           ${C_DIM}# solo backend de las 3 dev${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Los volúmenes nombrados NO se borran (usar ${C_RESET}${C_CYAN}rm-volumes${C_RESET}${C_DIM} para eso).${C_RESET}\n"
}

cmd_help_restart() {
    _registry_header "restart" "reiniciar containers"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}restart${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA REINICIAR TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart${C_RESET}                              ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart dev${C_RESET}                          ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart dev backend${C_RESET}                  ${C_DIM}# 1 target${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart production:panda-and-kitsune${C_RESET} ${C_DIM}# 1 instancia${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}rs${C_RESET}\n"
}

cmd_help_build() {
    _registry_header "build" "rebuild imágenes (--no-cache)"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}build${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA REBUILD TODO${C_RESET}\n"
    printf "  ${C_CYAN}$0 build${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 build${C_RESET}                                    ${C_DIM}# 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 build dev${C_RESET}                                ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 build dev backend${C_RESET}                        ${C_DIM}# 1 target${C_RESET}\n"
    printf "  ${C_CYAN}$0 build production:panda-and-kitsune frontend${C_RESET}  ${C_DIM}# 1 instancia${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}b${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Si querés limpiar el cache de Docker antes: ${C_RESET}${C_CYAN}docker builder prune${C_RESET}\n"
}

cmd_help_down() {
    _registry_header "down" "detener y eliminar containers (preserva volúmenes)"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}down${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA BAJAR TODO${C_RESET}\n"
    printf "  ${C_CYAN}$0 down${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 down${C_RESET}                       ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 down dev${C_RESET}                   ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 down production${C_RESET}            ${C_DIM}# las 3 prod${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Los volúmenes nombrados (postgres_data, storage, etc.) NO se borran.${C_RESET}\n"
    printf "  ${C_DIM}Para eso, usar ${C_RESET}${C_CYAN}rm-volumes${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_nuke() {
    _registry_header "nuke" "down + eliminar volúmenes (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_RED}${C_BOLD}⚠ DESTRUCTIVO${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}nuke${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 nuke dev${C_RESET}                       ${C_RED}# ⚠ borra TODOS los volúmenes de las 3 dev${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}QUÉ BORRA${C_RESET}\n"
    printf "  ${C_DIM}·${C_RESET} bases Postgres (todos los tenants)\n"
    printf "  ${C_DIM}·${C_RESET} storage de Laravel\n"
    printf "  ${C_DIM}·${C_RESET} node_modules de pnpm\n"
    printf "  ${C_DIM}·${C_RESET} caches de Astro / Vite\n"
    printf "\n"
    printf "${C_BOLD}DESPUÉS DE NUKE${C_RESET}\n"
    printf "  ${C_DIM}Hay que correr ${C_RESET}${C_CYAN}setup dev${C_RESET}${C_DIM} para recrear las 6 instancias.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Inspección
# ─────────────────────────────────────────────────────────────

cmd_help_status() {
    _registry_header "status" "compose ps de cada instancia"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}status${C_RESET} ${C_GREEN}[<área>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA VER ESTADO DE TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 status${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 status${C_RESET}                       ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 status dev${C_RESET}                   ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}SALIDA${C_RESET}\n"
    printf "  ${C_DIM}Lista service, state, ports por container. Para vista detallada${C_RESET}\n"
    printf "  ${C_DIM}(image, command, networks) usar ${C_RESET}${C_CYAN}info${C_RESET}${C_DIM} en su lugar.${C_RESET}\n"
}

cmd_help_ps() {
    _registry_header "ps" "lista rápida de containers (vista docker ps)"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}ps${C_RESET} ${C_GREEN}[<área>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA VER TODOS LOS CONTAINERS${C_RESET}\n"
    printf "  ${C_CYAN}$0 ps${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 ps${C_RESET}            ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 ps dev${C_RESET}        ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}ls${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}DIFERENCIA CON status${C_RESET}\n"
    printf "  ${C_DIM}ps = vista rápida de ${C_RESET}${C_CYAN}docker ps${C_RESET}${C_DIM}. status = compose ps por instancia.${C_RESET}\n"
    printf "  ${C_DIM}Para detalles por container, ${C_RESET}${C_CYAN}info${C_RESET}${C_DIM}.${C_RESET}\n"
}

cmd_help_info() {
    _registry_header "info" "tabla detallada: instancia · servicio · estado · puertos"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}info${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA VER INFO DE TODOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 info${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 info${C_RESET}                         ${C_DIM}# las 6${C_RESET}\n"
    printf "  ${C_CYAN}$0 info dev${C_RESET}                     ${C_DIM}# las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 info dev nginx${C_RESET}              ${C_DIM}# nginx de las 3 dev${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}psv${C_RESET}\n"
}

cmd_help_logs() {
    _registry_header "logs" "tail de logs de containers"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}logs${C_RESET} ${C_GREEN}<instancia> [<servicio>]${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}INSTANCIA${C_RESET}  ${C_DIM}(obligatoria — no hay 'all')${C_RESET}\n"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev (no recomendado: mucho spam)"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 logs development:panda-and-kitsune${C_RESET}             ${C_DIM}# 1 instancia${C_RESET}\n"
    printf "  ${C_CYAN}$0 logs dev backend${C_RESET}                              ${C_DIM}# 3 dev, backend${C_RESET}\n"
    printf "  ${C_CYAN}$0 logs production:morales-store app${C_RESET}             ${C_DIM}# 1 instancia + servicio${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ATAJOS${C_RESET}\n"
    printf "  ${C_DIM}Ctrl-C sale del tail.${C_RESET}\n"
    printf "  ${C_DIM}Para flags de compose (--since, --tail), pasarlos al final.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Volumes
# ─────────────────────────────────────────────────────────────

cmd_help_volumes() {
    _registry_header "volumes" "listar volúmenes Docker declarados por las instancias"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}volumes${C_RESET} ${C_GREEN}[<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA VER TODOS LOS VOLÚMENES${C_RESET} ${C_RED}${C_BOLD}(la única forma)${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}                                    ${C_DIM}# 6 instancias${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}                                    ${C_DIM}# DEFAULT = all${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes dev${C_RESET}                            ${C_DIM}# 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes dev backend${C_RESET}                    ${C_DIM}# backend de las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes production${C_RESET}                     ${C_DIM}# 3 prod${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes production:morales-store${C_RESET}       ${C_DIM}# 1 instancia prod${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}vol${C_RESET}\n"
}

cmd_help_rm_volumes() {
    _registry_header "rm-volumes" "eliminar volúmenes (⚠ BORRA DATOS)"
    printf "\n"
    printf "${C_RED}${C_BOLD}⚠ DESTRUCTIVO${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}rm-volumes${C_RESET} ${C_GREEN}[-y] [<área>] [<servicio>]${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}FLAGS${C_RESET}\n"
    printf "  ${C_CYAN}-y${C_RESET}    ${C_DIM}saltear confirmación interactiva (usar con cuidado)${C_RESET}\n"
    printf "\n"
    _registry_area_block
    printf "\n"
    printf "${C_BOLD}PARA BORRAR TODO${C_RESET}\n"
    printf "  ${C_CYAN}$0 rm-volumes -y${C_RESET} ${C_BOLD}${C_YELLOW}all${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 rm-volumes dev${C_RESET}                          ${C_DIM}# pedir confirmación${C_RESET}\n"
    printf "  ${C_CYAN}$0 rm-volumes -y dev backend${C_RESET}               ${C_DIM}# sin confirmación, 1 target${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ALIAS${C_RESET}: ${C_CYAN}rmvol${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Exec
# ─────────────────────────────────────────────────────────────

cmd_help_exec() {
    _registry_header "exec" "ejecutar comando dentro de un container"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}exec${C_RESET} ${C_GREEN}<instancia> <servicio> <comando...>${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}INSTANCIA${C_RESET}  ${C_DIM}(obligatoria — no hay 'all' para exec)${C_RESET}\n"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"     "→ 1 instancia prod"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"    "→ 1 instancia dev"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 exec development:mm-boutique app bash${C_RESET}\n"
    printf "  ${C_CYAN}$0 exec dev backend php artisan migrate --seed${C_RESET}\n"
    printf "  ${C_CYAN}$0 exec production:panda-and-kitsune app ls -la /var/www/html${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Si el servicio tiene varios containers (ej: nginx + php-fpm),${C_RESET}\n"
    printf "  ${C_DIM}toma el principal. Para uno específico:${C_RESET}\n"
    printf "  ${C_CYAN}  $0 exec <inst> <svc> --index N <comando>${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Setup
# ─────────────────────────────────────────────────────────────

cmd_help_setup() {
    _registry_header "setup" "primera instalación: copiar .env y levantar"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}setup${C_RESET} ${C_GREEN}[<área>]${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ÁREA${C_RESET}  ${C_DIM}(default ${C_BOLD}${C_YELLOW}all${C_RESET}${C_DIM} → preparar las 6)${C_RESET}\n"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                    "→ las 6 instancias"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "dev"                    "→ las 3 dev"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "prod"                   "→ las 3 prod"
    printf "\n"
    printf "${C_BOLD}QUÉ HACE${C_RESET}\n"
    printf "  ${C_DIM}1. copia ${C_RESET}${C_CYAN}docker/instances/<stage>/<store>/environment/.env.example${C_RESET}${C_DIM} a .env${C_RESET}\n"
    printf "  ${C_DIM}2. construye las imágenes${C_RESET}\n"
    printf "  ${C_DIM}3. levanta los containers${C_RESET}\n"
    printf "  ${C_DIM}4. corre migraciones y seeders base${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 setup${C_RESET}              ${C_DIM}# preparar las 6 (default = all)${C_RESET}\n"
    printf "  ${C_CYAN}$0 setup all${C_RESET}          ${C_DIM}# igual al anterior, explícito${C_RESET}\n"
    printf "  ${C_CYAN}$0 setup dev${C_RESET}          ${C_DIM}# preparar las 3 dev${C_RESET}\n"
}

cmd_help_hosts() {
    _registry_header "hosts" "imprime el bloque para /etc/hosts"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}hosts${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}EJEMPLOS${C_RESET}\n"
    printf "  ${C_CYAN}$0 hosts${C_RESET}                              ${C_DIM}# imprime líneas${C_RESET}\n"
    printf "  ${C_CYAN}sudo ./scripts/setup-local.sh${C_RESET}        ${C_DIM}# automágico${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Lista de IPs internas que Caddy del host necesita para resolver.${C_RESET}\n"
}

cmd_help_caddy() {
    _registry_header "caddy" "imprime el bloque Caddyfile"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}caddy${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}NOTA${C_RESET}\n"
    printf "  ${C_DIM}Bloque Caddy (puerto 80/443) para los dominios de las instancias.${C_RESET}\n"
    printf "  ${C_RED}${C_DIM}Si usás docker-compose de Caddy, IGNORAR.${C_RESET}\n"
}

# ─────────────────────────────────────────────────────────────
# Help
# ─────────────────────────────────────────────────────────────

cmd_help_help() {
    _registry_header "help" "mostrar ayuda"
    printf "\n"
    printf "${C_BOLD}USO${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}help${C_RESET}                       ${C_DIM}# global${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_GREEN}<comando>${C_RESET} ${C_YELLOW}help${C_RESET}             ${C_DIM}# per-comando${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_GREEN}<comando>${C_RESET} ${C_GREEN}<área>${C_RESET} ${C_YELLOW}help${C_RESET}    ${C_DIM}# per-comando + área${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}ATAJOS${C_RESET}\n"
    printf "  ${C_CYAN}-h${C_RESET}, ${C_CYAN}--help${C_RESET} en cualquier posición se tratan como ${C_CYAN}help${C_RESET}.\n"
    printf "\n"
    printf "${C_BOLD}DESCUBRIR MÁS${C_RESET}\n"
    printf "  ${C_CYAN}$0 help${C_RESET}                ${C_DIM}# este mensaje${C_RESET}\n"
    printf "  ${C_CYAN}$0 <comando> help${C_RESET}    ${C_DIM}# detalles del comando (uso, ejemplos, all emphasis)${C_RESET}\n"
}
