#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help
# Pantalla de ayuda con todos los comandos, scopes, instancias y
# ejemplos del orquestador docker-stack.
cmd_help() {
    /usr/bin/cat <<'SEPARATOR'

SEPARATOR

    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "${C_BOLD}  scripts/docker-stack.sh${C_RESET}  —  orquestador de las 6 instancias Docker\n"
    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_YELLOW}<comando>${C_RESET} ${C_GREEN}[args...]${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Comandos principales${C_RESET}  (todos aceptan scope + servicio opcional)\n"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "start"   "[scope] [svc]"      "Levantar containers (preserva volúmenes)"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "stop"    "[scope] [svc]"      "Detener containers (preserva volúmenes)"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "restart" "[scope] [svc]"      "Reiniciar containers"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "up"      "[scope] [svc]"      "build + start (convenio docker compose)"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "build"   "[scope] [svc]"      "Reconstruir imágenes (--no-cache)"
    printf "  ${C_GREEN}%-10s${C_RESET} ${C_GREEN}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "down"    "[scope] [svc]"      "Detener y eliminar containers (preserva volúmenes)"
    printf "  ${C_RED}%-10s${C_RESET} ${C_RED}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "nuke"    "[scope] [svc]"      "⚠ down + eliminar volúmenes (BORRA DATOS)"
    printf "\n"
    printf "${C_BOLD}Información${C_RESET}\n"
    printf "  ${C_BLUE}%-10s${C_RESET} ${C_BLUE}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "status"  "[scope]"            "Estado de cada servicio (compose ps)"
    printf "  ${C_BLUE}%-10s${C_RESET} ${C_BLUE}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "ps"      ""                   "Lista de containers (vista rápida)"
    printf "  ${C_BLUE}%-10s${C_RESET} ${C_BLUE}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "info"    "[scope] [svc]"      "Tabla detallada: instancia, servicio, estado,"
    printf "  ${C_BLUE}%-10s${C_RESET} ${C_BLUE}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "psv"     ""                   "  puertos y comando del container"
    printf "\n"
    printf "${C_BOLD}Logs y ejecución${C_RESET}\n"
    printf "  ${C_MAGENTA}%-10s${C_RESET} ${C_MAGENTA}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "logs"    "[instancia] [svc]" "Tail de logs (default: todas las instancias)"
    printf "  ${C_MAGENTA}%-10s${C_RESET} ${C_MAGENTA}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "exec"    "<inst> <svc> <cmd>" "Ejecuta <cmd> dentro del container"
    printf "\n"
    printf "${C_BOLD}Volúmenes${C_RESET}\n"
    printf "  ${C_BLUE}%-10s${C_RESET} ${C_BLUE}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "volumes"     "[scope] [svc]" "Lista volúmenes Docker (driver, mountpoint, size)"
    printf "  ${C_RED}%-10s${C_RESET} ${C_RED}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "rm-volumes"  "[-y] [scope] [svc]" "⚠ Elimina volúmenes (pide confirmación o -y)"
    printf "\n"
    printf "${C_BOLD}Setup y ayuda${C_RESET}\n"
    printf "  ${C_YELLOW}%-10s${C_RESET} ${C_YELLOW}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "setup"   "[prod|dev|all]"    "Primera instalación: copia .env.example, levanta"
    printf "  ${C_YELLOW}%-10s${C_RESET} ${C_YELLOW}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "hosts"   ""                   "Imprime el bloque para /etc/hosts"
    printf "  ${C_YELLOW}%-10s${C_RESET} ${C_YELLOW}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "caddy"   ""                   "Imprime el Caddyfile a usar"
    printf "  ${C_YELLOW}%-10s${C_RESET} ${C_YELLOW}%-25s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "help"    ""                   "Muestra esta ayuda"
    printf "\n"
    printf "${C_BOLD}Aliases cortos${C_RESET}\n"
    printf "  ${C_CYAN}b${C_RESET}=build   ${C_CYAN}rs${C_RESET}=restart   ${C_CYAN}ls${C_RESET}=ps   ${C_CYAN}psv${C_RESET}=info   ${C_CYAN}vol${C_RESET}=volumes   ${C_CYAN}rmvol${C_RESET}=rm-volumes\n"
    printf "\n"

    printf "${C_BOLD}Scopes${C_RESET} (operan sobre múltiples instancias a la vez)\n"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                   "las 6 instancias (3 prod + 3 dev)"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production / prod"     "las 3 instancias de production"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development / dev"     "las 3 instancias de development"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "<store>"               "1 instancia, default = production"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:<store>"    "1 instancia de prod con scope explícito"
    printf "  ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:<store>"   "1 instancia de dev con scope explícito"
    printf "\n"

    printf "${C_BOLD}Instancias${C_RESET} (6 totales, separadas por scope)\n"
    printf "  ${C_BOLD}production${C_RESET} (3 prod, auto-contenidas)\n"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:panda-estampados" "multi-tenant: Pandas Estampados + Kitsune"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:mm-boutique"       "single-tenant"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:morales-store"     "single-tenant"
    printf "  ${C_BOLD}development${C_RESET} (3 dev, con bind mounts + xdebug)\n"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:panda-estampados" "BD aislada, puertos shifted +1000"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:mm-boutique"       "BD aislada, puertos shifted +2000"
    printf "    ${C_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:morales-store"     "BD aislada, puertos shifted +3000"
    printf "\n"

    printf "${C_BOLD}Ejemplos${C_RESET} (los más comunes)\n"
    printf "  ${C_CYAN}$0 start all${C_RESET}\n"
    printf "    ${C_DIM}levanta las 6 instancias (3 prod + 3 dev)${C_RESET}\n"
    printf "  ${C_CYAN}$0 start production${C_RESET}\n"
    printf "    ${C_DIM}levanta las 3 prod${C_RESET}\n"
    printf "  ${C_CYAN}$0 start development${C_RESET}\n"
    printf "    ${C_DIM}levanta las 3 dev en paralelo (puertos distintos)${C_RESET}\n"
    printf "  ${C_CYAN}$0 start dev${C_RESET}\n"
    printf "    ${C_DIM}atajo de 'start development'${C_RESET}\n"
    printf "  ${C_CYAN}$0 start development:mm-boutique${C_RESET}\n"
    printf "    ${C_DIM}levanta solo MM Boutique dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 build dev backend${C_RESET}\n"
    printf "    ${C_DIM}rebuild solo del backend de dev${C_RESET}\n"
    printf "  ${C_CYAN}$0 restart panda-estampados app${C_RESET}\n"
    printf "    ${C_DIM}reinicia solo el servicio 'app' de Panda prod${C_RESET}\n"
    printf "  ${C_CYAN}$0 logs development:mm-boutique frontend${C_RESET}\n"
    printf "    ${C_DIM}tail logs del frontend dev de mm-boutique${C_RESET}\n"
    printf "  ${C_CYAN}$0 exec development:morales-store app bash${C_RESET}\n"
    printf "    ${C_DIM}bash dentro del app dev de morales-store${C_RESET}\n"
    printf "  ${C_CYAN}$0 info${C_RESET}\n"
    printf "    ${C_DIM}tabla detallada de todos los containers${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes development:panda-estampados${C_RESET}\n"
    printf "    ${C_DIM}lista los volúmenes de panda dev (postgres_data, storage, etc.)${C_RESET}\n"
    printf "  ${C_CYAN}$0 volumes dev backend${C_RESET}\n"
    printf "    ${C_DIM}volúmenes del servicio backend de las 3 dev (filtra los relevantes)${C_RESET}\n"
    printf "  ${C_CYAN}$0 rm-volumes -y dev backend${C_RESET}\n"
    printf "    ${C_DIM}borra los volúmenes del backend de las 3 dev sin pedir confirmación${C_RESET}\n"
    printf "  ${C_CYAN}$0 setup${C_RESET}\n"
    printf "    ${C_DIM}primera instalación (copia .env.example + levanta)${C_RESET}\n"
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