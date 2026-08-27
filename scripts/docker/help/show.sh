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
#
# Layout:
#   comando  [flag]  <stage>  <store|all>  [<servicio>]   descripcion
#
# El padding se calcula en runtime con _help_pad() para que la columna
# de descripcion quede alineada aunque los comandos tengan distinta
# cantidad de columnas (ej: build tiene flag, fresh no).
cmd_help() {
    /usr/bin/cat <<'SEPARATOR'

SEPARATOR

    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "${C_BOLD}  scripts/docker-stack.sh${C_RESET}  —  orquestador de las 6 instancias Docker\n"
    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  ${C_CYAN}$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}<comando>${C_RESET} ${C_BRIGHT_GREEN}[flags]${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store|all>${C_RESET} ${C_BRIGHT_GREEN}[<servicio>]${C_RESET}\n"
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

    # Calcular el ancho maximo de las primeras 4 columnas (cmd, flag,
    # stage, store) para alinear las descripciones.
    # IMPORTANTE: el delimitador entre campos es `;` (no `|`), porque
    # `<store|all>` contiene un pipe literal que rompe el split.
    local rows=(
        # cmd;flag;stage;store;svc;desc
        "start;        ;<stage>;      <store|all>;      [<svc>];Levantar containers (preserva volúmenes)"
        "stop;         ;<stage>;      <store|all>;      [<svc>];Detener containers (preserva volúmenes)"
        "restart;      ;<stage>;      <store|all>;      [<svc>];Reiniciar containers"
        "up;           ;<stage>;      <store|all>;      [<svc>];build + start (recrea si .env cambia)"
        "build;       [no-cache];<stage>;            <store|all>;[<svc>];Reconstruir imágenes (con cache por default)"
        "down;         ;<stage>;      <store|all>;      [<svc>];Detener y eliminar containers (preserva volúmenes)"
        "nuke;         ;<stage>;      <store|all>;      [<svc>];⚠ down + eliminar volúmenes (BORRA DATOS)"
        "status;       ;<stage>;      <store|all>;      [<svc>];Estado de cada servicio (compose ps)"
        "ps;           ;              ;                 ;              ;Lista de containers (vista rápida, todas las instancias)"
        "info;         ;<stage>;      <store|all>;      [<svc>];Tabla detallada: imagen, comando, puertos, status"
        "logs;         ;<stage>;      <store|all>;      <servicio>;Tail de logs (servicio REQUERIDO)"
        "exec;         ;<stage>;      <store>;          <servicio> <cmd...>;Ejecuta <cmd> dentro del container"
        "volumes;      ;<stage>;      <store|all>;      [<svc>];Lista volúmenes Docker (driver, mountpoint, size)"
        "rm-volumes;  [-y];<stage>;   <store|all>;     [<svc>];⚠ Elimina volúmenes (pide confirmación o -y)"
        "setup;        ;<stage>;      <store|all>;      ;              Primera instalación: copia .env.example + levanta"
        "bootstrap;    ;<stage>;      <store>;          ;              Post-deploy: migrate, key:generate, caches (--with-seed, --with-backup)"
        "fresh;        ;<stage>;      <store>;          ;              ⚠ Fresh start: down -v + rebuild + reinit + migrate + seed"
        "db-backup;    ;<stage>;      <store>;          ;              Exporta BD a db-backups/<name>.sql.gz (--schema-only, --no-compress)"
        "db-restore;   ;<stage>;      <store>;          <archivo>;     Carga dump en BD con pre-backup safety net (--force, --truncate)"
        "hosts;        ;              ;                 ;              Imprime el bloque para /etc/hosts"
        "caddy;        ;              ;                 ;              Imprime el Caddyfile a usar"
        "help;         ;              ;                 ;              Muestra esta ayuda"
    )
    # Calcular anchos maximos por columna (5 primeras separadas por ;)
    local max_cmd=0 max_flag=0 max_stage=0 max_store=0 max_svc=0
    for row in "${rows[@]}"; do
        IFS=';' read -r cmd flag stage store svc desc <<< "$row"
        [[ ${#cmd}   -gt $max_cmd   ]] && max_cmd=${#cmd}
        [[ ${#flag}  -gt $max_flag  ]] && max_flag=${#flag}
        [[ ${#stage} -gt $max_stage ]] && max_stage=${#stage}
        [[ ${#store} -gt $max_store ]] && max_store=${#store}
        [[ ${#svc}   -gt $max_svc   ]] && max_svc=${#svc}
    done

    printf "${C_BOLD}Comandos${C_RESET}  (ancho calculado en runtime — siempre alineado)\n"
    for row in "${rows[@]}"; do
        IFS=';' read -r cmd flag stage store svc desc <<< "$row"
        # Color: rojo si destructivo.
        local cmd_color="${C_BOLD}${C_BRIGHT_MAGENTA}"
        if [[ "$cmd" == "nuke"* || "$cmd" == "fresh"* || "$cmd" == "rm-volumes"* || "$cmd" == "db-restore"* ]]; then
            cmd_color="${C_BOLD}${C_BRIGHT_RED}"
        fi
        local flag_color=""
        [[ -n "$flag" ]] && flag_color="${C_BOLD}${C_BRIGHT_RED}${flag}${C_RESET} "
        # Imprimir siempre 5 campos con padding fijo + separador `|`.
        # El svc puede ser vacio; en ese caso queda padding en blanco.
        printf "  ${cmd_color}%-${max_cmd}s${C_RESET} ${flag_color}%-${max_stage}s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-${max_store}s${C_RESET} ${C_BRIGHT_GREEN}%-${max_svc}s  |${C_RESET} ${C_DIM}%s${C_RESET}\n" \
            "$cmd" "$stage" "$store" "$svc" "$desc"
    done
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
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}bootstrap${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}\n"
    printf "    ${C_DIM}post-deploy: migrate + key:generate + caches (sin seeders)${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}db-backup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET}\n"
    printf "    ${C_DIM}exporta BD a db-backups/<store>-<ts>.sql.gz${C_RESET}\n"
    printf "  ${C_CYAN}\$0${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}db-restore${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados${C_RESET} ${C_BRIGHT_GREEN}<archivo>${C_RESET}\n"
    printf "    ${C_DIM}carga dump en BD con pre-backup safety net${C_RESET}\n"
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