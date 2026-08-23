#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help
# Pantalla de ayuda con todos los comandos, stages, stores y ejemplos
# del orquestador docker-stack.
#
# Paleta (consistente con registry.sh):
#   Path ($0)        → C_CYAN
#   Comando          → C_BOLD + C_BRIGHT_MAGENTA
#   Stage            → C_BOLD + C_BRIGHT_YELLOW
#   Store            → C_BOLD + C_BRIGHT_CYAN
#   Servicio         → C_BOLD + C_BRIGHT_GREEN
#   Flag             → C_BOLD + C_BRIGHT_RED
#   Destructivo      → C_BOLD + C_BRIGHT_RED
#   Comentario       → C_DIM
cmd_help() {
    /usr/bin/cat <<'SEPARATOR'

SEPARATOR

    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "${C_BOLD}  scripts/docker-stack.sh${C_RESET}  —  orquestador de las 6 instancias Docker\n"
    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<comando>${C_RESET} ${C_BRIGHT_GREEN}[flags]${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store|all>${C_RESET} ${C_BRIGHT_GREEN}[<servicio>]${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Stages${C_RESET}  (color yellow en ejemplos)\n"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production | prod"     "→ 3 instancias de producción"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development | dev"     "→ 3 instancias de development (bind mounts + xdebug)"
    printf "\n"
    printf "${C_BOLD}Stores${C_RESET}  (color cyan en ejemplos)\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "panda-estampados"      "→ 1 instancia (multi-tenant: Pandas + Kitsune)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "mm-boutique"           "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "morales-store"         "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                   "→ todas las tiendas del stage"
    printf "\n"

    printf "${C_BOLD}Comandos principales${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "start"    "<stage>"      "<store|all>"       "[<svc>]"       "Levantar containers (preserva volúmenes)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "stop"     "<stage>"      "<store|all>"       "[<svc>]"       "Detener containers (preserva volúmenes)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "restart"  "<stage>"      "<store|all>"       "[<svc>]"       "Reiniciar containers"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "up"       "<stage>"      "<store|all>"       "[<svc>]"       "build + start (convenio docker compose)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-10s${C_RESET} ${C_BRIGHT_GREEN}%-6s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "build"    "[no-cache]"   "<stage>"           "<store|all>"   "[<svc>]"  "Reconstruir imágenes (con cache por default)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "down"     "<stage>"      "<store|all>"       "[<svc>]"       "Detener y eliminar containers (preserva volúmenes)"
    printf "  ${C_BOLD}${C_BRIGHT_RED}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-20s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "nuke"     "<stage>"      "<store|all>"       "[<svc>]"       "⚠ down + eliminar volúmenes (BORRA DATOS)"
    printf "\n"

    printf "${C_BOLD}Información${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "status"   "<stage>"      "<store|all>"       "[<svc>]"       "Estado de cada servicio (compose ps)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_DIM}%-51s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "ps"       "(sin args)"                                "Lista de containers (vista rápida, todas las instancias)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "info"     "<stage>"      "<store|all>"       "[<svc>]"       "Tabla detallada: imagen, comando, puertos, status"
    printf "\n"

    printf "${C_BOLD}Logs y ejecución${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "logs"     "<stage>"      "<store|all>"       "<servicio>"    "Tail de logs (servicio REQUERIDO)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-30s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "exec"     "<stage>"      "<store>"           "<servicio> <cmd...>" "Ejecuta <cmd> dentro del container"
    printf "\n"

    printf "${C_BOLD}Volúmenes${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_BRIGHT_GREEN}%-13s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "volumes"      "<stage>"     "<store|all>"       "[<svc>]"       "Lista volúmenes Docker (driver, mountpoint, size)"
    printf "  ${C_BOLD}${C_BRIGHT_RED}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-10s${C_RESET} ${C_BRIGHT_GREEN}%-6s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "rm-volumes"   "[-y]"        "<stage>"           "<store|all>"   "[<svc>]"  "⚠ Elimina volúmenes (pide confirmación o -y)"
    printf "\n"

    printf "${C_BOLD}Setup y ayuda${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "setup"    "<stage>"      "<store|all>"       "Primera instalación: copia .env.example, levanta"
    printf "  ${C_BOLD}${C_BRIGHT_RED}%-10s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-15s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-20s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "fresh"    "<stage>"      "<store>"           "⚠ Fresh start de 1 instancia (borra volumes + rebuild + reinit)"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_DIM}%-51s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "hosts"    ""                                       "Imprime el bloque para /etc/hosts"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_DIM}%-51s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "caddy"    ""                                       "Imprime el Caddyfile a usar"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}%-10s${C_RESET} ${C_DIM}%-51s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "help"     ""                                       "Muestra esta ayuda"
    printf "\n"

    printf "${C_BOLD}Aliases cortos${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}b${C_RESET}=build   ${C_BOLD}${C_BRIGHT_MAGENTA}rs${C_RESET}=restart   ${C_BOLD}${C_BRIGHT_MAGENTA}ls${C_RESET}=ps   ${C_BOLD}${C_BRIGHT_MAGENTA}psv${C_RESET}=info   ${C_BOLD}${C_BRIGHT_MAGENTA}vol${C_RESET}=volumes   ${C_BOLD}${C_BRIGHT_MAGENTA}rmvol${C_RESET}=rm-volumes\n"
    printf "\n"

    printf "${C_BOLD}Instancias${C_RESET} (6 totales = 3 prod + 3 dev)\n"
    printf "  ${C_BOLD}production${C_RESET} (3 prod, auto-contenidas)\n"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:panda-estampados" "multi-tenant: Pandas Estampados + Kitsune"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:mm-boutique"      "single-tenant"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:morales-store"    "single-tenant"
    printf "  ${C_BOLD}development${C_RESET} (3 dev, con bind mounts + xdebug)\n"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:panda-estampados" "BD aislada, puertos shifted +1000"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:mm-boutique"      "BD aislada, puertos shifted +2000"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:morales-store"    "BD aislada, puertos shifted +3000"
    printf "\n"

    printf "${C_BOLD}Ejemplos${C_RESET} (los más comunes)\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n"
    printf "    ${C_DIM}build + start de las 3 instancias dev${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n"
    printf "    ${C_DIM}build + start de las 3 instancias prod${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n"
    printf "    ${C_DIM}rebuild del backend de Panda dev (con cache)${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n"
    printf "    ${C_DIM}rebuild completo sin cache de 1 target${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET}\n"
    printf "    ${C_DIM}reinicia MM Boutique prod${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n"
    printf "    ${C_DIM}tail paralelo del backend de las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET} ${C_BRIGHT_GREEN}bash${C_RESET}\n"
    printf "    ${C_DIM}bash dentro del backend de Panda dev${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BOLD}${C_BRIGHT_RED}-y${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}\n"
    printf "    ${C_DIM}borra los volúmenes de Panda dev sin pedir confirmación${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n"
    printf "    ${C_DIM}primera instalación de las 3 dev (copia .env.example + levanta)${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}fresh${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}\n"
    printf "    ${C_DIM}fresh start de Panda dev (borra volumes + rebuild + reinit)${C_RESET}\n"
    printf "\n"

    printf "${C_BOLD}Puertos dev${C_RESET} (cada tienda usa puertos shifted distintos para correr las 3 en paralelo)\n"
    printf "  ${C_BOLD}%-22s %6s %6s %8s %6s %5s %13s${C_RESET}\n" \
        "Tienda" "PG" "Redis" "Backend" "Front" "FTP" "PASV"
    printf "  ${C_DIM}%-22s %6s %6s %8s %6s %5s %13s${C_RESET}\n" \
        "panda-estampados-dev" "6432" "7379" "9000" "5321" "2121" "40000-40099"
    printf "  ${C_DIM}%-22s %6s %6s %8s %6s %5s %13s${C_RESET}\n" \
        "mm-boutique-dev"       "7432" "8379" "10000" "6321" "3121" "40100-40199"
    printf "  ${C_DIM}%-22s %6s %6s %8s %5s %13s${C_RESET}\n" \
        "morales-store-dev"     "8432" "9379" "11000" "7321" "4121" "40200-40299"
    printf "\n"
    printf "  ${C_DIM}Definidos en docker/instances/development/<store>/environment/.env${C_RESET}\n"
}
