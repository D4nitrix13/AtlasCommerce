#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help
# Pantalla de ayuda con todos los comandos del orquestador.
#
# Layout vertical (cada comando en 2 lineas: una con el comando,
# otra con la descripcion). Mas legible que tablas horizontales con
# padding dinamico porque no depende del ancho del terminal ni de
# caracteres ANSI.
cmd_help() {
    /usr/bin/cat <<'SEPARATOR'

SEPARATOR

    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "${C_BOLD}  scripts/docker-stack.sh${C_RESET}  —  orquestador de las 6 instancias Docker\n"
    printf "${C_BOLD}${C_CYAN}══════════════════════════════════════════════════════════════════════════${C_RESET}\n"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${C_BOLD}${C_BRIGHT_MAGENTA}<comando>${C_RESET} ${C_BRIGHT_GREEN}[flags]${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}<store|all>${C_RESET} ${C_BRIGHT_GREEN}[<servicio>]${C_RESET}\n" "$0"
    printf "\n"
    printf "${C_BOLD}Stages${C_RESET}  (color yellow en ejemplos)\n"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production | prod"     "→ 3 instancias de producción"
    printf "  ${C_BOLD}${C_BRIGHT_YELLOW}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development | dev"     "→ 3 instancias de development (bind mounts + xdebug)"
    printf "\n"
    printf "${C_BOLD}Stores${C_RESET}  (color cyan en ejemplos)\n"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "panda-estampados-and-kitsune"      "→ 1 instancia (multi-tenant: Pandas + Kitsune)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "mm-boutique"           "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "morales-store"         "→ 1 instancia (single-tenant)"
    printf "  ${C_BOLD}${C_BRIGHT_CYAN}%-22s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "all"                   "→ todas las tiendas del stage"
    printf "\n"

    # Tabla data-driven de comandos. Delimitador `;` (no `|`) porque
    # `<store|all>` tiene un pipe literal que rompe el split con IFS='|'.
    # Formato de cada fila: cmd;flag;stage;store;svc;descripcion
    #
    # Layout vertical en 2 lineas: la firma (cmd + flag + stage + store + svc)
    # en una linea con anchos FIJOS por columna, y la descripcion indentada
    # abajo. Anchos fijos (no calcularlos en runtime) para que la firma
    # quede alineada aunque el flag este presente o no.
    #   cmd  = 12  (cabe "db-restore" = 10)
    #   flag = 12  (cabe "[no-cache]" = 10)
    #   stage=  7  (= "<stage>")
    #   store= 14  (cabe "<store|all>" = 10)
    #   svc  = 20  (cabe "<servicio> <cmd...>" = 19)
    local rows=(
        "start;;<stage>;<store|all>;[<svc>];Levantar containers (preserva volúmenes)"
        "stop;;<stage>;<store|all>;[<svc>];Detener containers (preserva volúmenes)"
        "restart;;<stage>;<store|all>;[<svc>];Reiniciar containers"
        "up;;<stage>;<store|all>;[<svc>];build + start (recrea si .env cambia)"
        "build;[no-cache];<stage>;<store|all>;[<svc>];Reconstruir imágenes (con cache por default)"
        "down;;<stage>;<store|all>;[<svc>];Detener y eliminar containers (preserva volúmenes)"
        "nuke;;<stage>;<store|all>;[<svc>];⚠ down + eliminar volúmenes (BORRA DATOS)"
        "status;;<stage>;<store|all>;[<svc>];Estado de cada servicio (compose ps)"
        "ps;;;;;Lista de containers (vista rápida, todas las instancias)"
        "info;;<stage>;<store|all>;[<svc>];Tabla detallada: imagen, comando, puertos, status"
        "logs;;<stage>;<store|all>;<servicio>;Tail de logs (servicio REQUERIDO)"
        "exec;;<stage>;<store>;<servicio> <cmd...>;Ejecuta <cmd> dentro del container"
        "volumes;;<stage>;<store|all>;[<svc>];Lista volúmenes Docker agrupados por categoría (data/cache/log) con tamaño real"
        "rm-volumes;[-y];<stage>;<store|all>;[<svc>];⚠ Elimina volúmenes (pide confirmación o -y)"
        "setup;;<stage>;<store|all>;;Primera instalación: copia .env.example + levanta"
        "bootstrap;;<stage>;<store>;;Post-deploy: migrate, key:generate, caches (--with-seed, --with-backup)"
        "fresh;;<stage>;<store>;;⚠ Fresh start: down -v + rebuild + reinit + migrate + seed"
        "db-backup;;<stage>;<store>;;Exporta BD a db-backups/<name>.sql.gz (--schema-only, --no-compress)"
        "db-restore;;<stage>;<store>;<archivo>;Carga dump en BD con pre-backup safety net (--force, --truncate)"
        "sync-assets;;<stage>;<store|all>;;Sincroniza brand-assets/ al FTP via php artisan (--dry-run, --force)"
        "seed-db;;<stage>;<store>;;Migra + popula BD con seeders y/o SQL dump (--seed, --sql <file>)"
        "hosts;;;;;Imprime el bloque para /etc/hosts"
        "caddy;;;;;Imprime el Caddyfile a usar"
        "help;;;;;Muestra esta ayuda"
    )

    printf "${C_BOLD}Comandos${C_RESET}  (layout vertical — descripción abajo del comando)\n"
    for row in "${rows[@]}"; do
        IFS=';' read -r cmd flag stage store svc desc <<< "$row"
        # Rojo si es destructivo (borra datos).
        local cmd_color="${C_BOLD}${C_BRIGHT_MAGENTA}"
        if [[ "$cmd" == "nuke"* || "$cmd" == "fresh"* || "$cmd" == "rm-volumes"* || "$cmd" == "db-restore"* ]]; then
            cmd_color="${C_BOLD}${C_BRIGHT_RED}"
        fi
        # Firma en una sola linea con anchos fijos. Asi la columna de
        # stage queda en la misma posicion aunque el flag este o no.
        printf "  ${cmd_color}%-12s${C_RESET} ${C_BOLD}${C_BRIGHT_RED}%-12s${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}%-7s${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}%-14s${C_RESET} ${C_BRIGHT_GREEN}%-20s${C_RESET}\n" \
            "$cmd" "$flag" "$stage" "$store" "$svc"
        # Descripcion indentada 6 cols (4 cols a la derecha del cmd).
        printf "      ${C_DIM}%s${C_RESET}\n" "$desc"
    done
    printf "\n"

    printf "${C_BOLD}Aliases cortos${C_RESET}\n"
    printf "  ${C_BOLD}${C_BRIGHT_MAGENTA}b${C_RESET}=build   ${C_BOLD}${C_BRIGHT_MAGENTA}rs${C_RESET}=restart   ${C_BOLD}${C_BRIGHT_MAGENTA}ls${C_RESET}=ps   ${C_BOLD}${C_BRIGHT_MAGENTA}psv${C_RESET}=info   ${C_BOLD}${C_BRIGHT_MAGENTA}vol${C_RESET}=volumes   ${C_BOLD}${C_BRIGHT_MAGENTA}rmvol${C_RESET}=rm-volumes\n"
    printf "\n"

    printf "${C_BOLD}Instancias${C_RESET} (6 totales = 3 prod + 3 dev)\n"
    printf "  ${C_BOLD}production${C_RESET} (3 prod, auto-contenidas)\n"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:panda-estampados-and-kitsune" "multi-tenant: Pandas Estampados + Kitsune"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:mm-boutique"      "single-tenant"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "production:morales-store"    "single-tenant"
    printf "  ${C_BOLD}development${C_RESET} (3 dev, con bind mounts + xdebug)\n"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:panda-estampados-and-kitsune" "BD aislada, puertos shifted +1000"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:mm-boutique"      "BD aislada, puertos shifted +2000"
    printf "    ${C_BOLD}${C_BRIGHT_CYAN}%-32s${C_RESET} ${C_DIM}%s${C_RESET}\n" \
        "development:morales-store"    "BD aislada, puertos shifted +3000"
    printf "\n"

    # Ejemplos: usar $0 sin escapar para que bash lo expanda al path
    # real del script (ej: ./scripts/docker-stack.sh). Antes se imprimia
    # `$0` literal porque estaba escapado con \$0 dentro del format
    # string del printf.
    printf "${C_BOLD}Ejemplos${C_RESET} (los más comunes)\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n" "$0"
    printf "    ${C_DIM}build + start de las 3 instancias dev${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}up${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n" "$0"
    printf "    ${C_DIM}build + start de las 3 instancias prod${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n" "$0"
    printf "    ${C_DIM}rebuild del backend de Panda dev (con cache)${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}build${C_RESET} ${C_BOLD}${C_BRIGHT_RED}no-cache${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n" "$0"
    printf "    ${C_DIM}rebuild completo sin cache de 1 target${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}restart${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}production${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}mm-boutique${C_RESET}\n" "$0"
    printf "    ${C_DIM}reinicia MM Boutique prod${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}logs${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET}\n" "$0"
    printf "    ${C_DIM}tail paralelo del backend de las 3 dev${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}exec${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET} ${C_BOLD}${C_BRIGHT_GREEN}backend${C_RESET} ${C_BRIGHT_GREEN}bash${C_RESET}\n" "$0"
    printf "    ${C_DIM}bash dentro del backend de Panda dev${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} ${C_BOLD}${C_BRIGHT_RED}-y${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}\n" "$0"
    printf "    ${C_DIM}borra los volúmenes de Panda dev sin pedir confirmación${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}setup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}all${C_RESET}\n" "$0"
    printf "    ${C_DIM}primera instalación de las 3 dev (copia .env.example + levanta)${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}bootstrap${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}\n" "$0"
    printf "    ${C_DIM}post-deploy: migrate + key:generate + caches (sin seeders)${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}db-backup${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}\n" "$0"
    printf "    ${C_DIM}exporta BD a db-backups/<store>-<ts>.sql.gz${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}db-restore${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}dev${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET} ${C_BRIGHT_GREEN}<archivo>${C_RESET}\n" "$0"
    printf "    ${C_DIM}carga dump en BD con pre-backup safety net${C_RESET}\n"
    printf "  ${C_CYAN}%s${C_RESET} ${C_BOLD}${C_BRIGHT_MAGENTA}fresh${C_RESET} ${C_BOLD}${C_BRIGHT_YELLOW}development${C_RESET} ${C_BOLD}${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}\n" "$0"
    printf "    ${C_DIM}fresh start de Panda dev (borra volumes + rebuild + reinit)${C_RESET}\n"
    printf "\n"

printf "${C_BOLD}Puertos dev${C_RESET} (cada tienda usa puertos shifted distintos para correr las 3 en paralelo)\n"
    # Anchos por columna = max(len(header), len(values)) + 1 espacio de padding.
    # Tienda=25 (izquierda, nombre), resto right-aligned.
    # Valores: PG=4 / Redis=4 / Backend=5 / Front=4 / FTP=4 / PASV=11 / pgAdm=4 / Insig=4 / FTPbr=5.
    printf "  ${C_BOLD}%-25s %5s %6s %8s %5s %5s %13s %6s %6s %6s${C_RESET}\n" \
        "Tienda" " PG" " Redis" " Backend" " Front" "  FTP" "         PASV" " pgAdm" " Insig" " FTPbr"
    printf "  ${C_DIM}%-25s %5s %6s %8s %5s %5s %13s %6s %6s %6s${C_RESET}\n" \
        "panda-estampados-and-kitsune-dev" "6432" " 7379" "   9000" "5321" "2121" "40000-40099" " 6050" " 6540" "  9080"
    printf "  ${C_DIM}%-25s %5s %6s %8s %5s %5s %13s %6s %6s %6s${C_RESET}\n" \
        "mm-boutique-dev"              "7432" " 8379" "  10000" "6321" "3121" "40100-40199" " 7050" " 7540" " 10080"
    printf "  ${C_DIM}%-25s %5s %6s %8s %5s %5s %13s %6s %6s %6s${C_RESET}\n" \
        "morales-store-dev"            "8432" " 9379" "  11000" "7321" "4121" "40200-40299" " 8050" " 8540" " 11080"
    printf "\n"
    printf "  ${C_DIM}pgAdm=pgAdmin:5050  Insig=RedisInsight:5540  FTPbr=filebrowser:8080 (todos shifted por instancia)${C_RESET}\n"
    printf "  ${C_DIM}Definidos en docker/instances/development/<store>/environment/.env${C_RESET}\n"

    printf "\n"
    printf "${C_BOLD}Sub-tenants${C_RESET} (multi-tenancy: varias tiendas comparten la misma instancia)\n"
    printf "  ${C_DIM}panda-estampados-and-kitsune-dev hospeda 2 tenants:${C_RESET}\n"
    printf "    ${C_BRIGHT_CYAN}pandas-estampados.test${C_RESET} ${C_DIM}(B2B mayorista)${C_RESET}\n"
    printf "    ${C_BRIGHT_CYAN}kitsune.test${C_RESET}         ${C_DIM}(B2C minorista)${C_RESET}\n"
    printf "  ${C_DIM}Ambos comparten containers backend, postgres, redis, ftp. Mismos puertos de la instancia padre.${C_RESET}\n"
}