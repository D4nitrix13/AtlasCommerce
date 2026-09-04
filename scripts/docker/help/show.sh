#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help [comando]
#
# Pantalla de ayuda del orquestador Docker.
#
# Formas de uso:
#   ./scripts/docker-stack.sh help            -> índice compacto de todos los comandos
#   ./scripts/docker-stack.sh help <comando>  -> ayuda detallada de un comando específico
#   ./scripts/docker-stack.sh <cmd> --help    -> atajo del anterior
#
# Implementación: tablas Markdown-style (|---|) generadas manualmente
# con `printf`. Anchos calculados con `_ansi_strip` para que los colores
# ANSI en los nombres de comando NO rompan la alineación.
#
# Notas de estilo:
#   - Sin emojis, sin "(color X en ejemplos)", sin ruido.
#   - Comandos coloreados: magenta para normales, rojo brillante para
#     destructivos. Color configurable vía _BOX_CMD_COLOR antes de cada
#     tabla.

cmd_help() {
    local subcmd="${1:-}"

    if [[ -n "$subcmd" ]]; then
        _cmd_specific_help "$subcmd"
        return 0
    fi

    # ─── Help raíz: índice compacto ──────────────────────────────────
    /usr/bin/cat <<'SEPARATOR'

SEPARATOR

    printf "${C_BOLD}scripts/docker-stack.sh${C_RESET}  orquestador de las 6 instancias Docker\n"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${C_BRIGHT_MAGENTA}<comando>${C_RESET} [flags] ${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BRIGHT_CYAN}<store|all>${C_RESET} [<servicio>]\n" "$0"
    printf "  %s ${C_BRIGHT_MAGENTA}<comando>${C_RESET} ${C_DIM}--help${C_RESET}  help detallado del comando\n" "$0"
    printf "\n"
    printf "${C_BOLD}Argumentos${C_RESET}\n"
    printf "  ${C_BRIGHT_YELLOW}<stage>${C_RESET}        production | prod | development | dev\n"
    printf "  ${C_BRIGHT_CYAN}<store|all>${C_RESET}   panda-estampados-and-kitsune | mm-boutique | morales-store | all\n"
    printf "  [<servicio>]   (opcional) backend | frontend | postgres | redis | caddy | ...\n"
    printf "\n"

    # ─── Índice de comandos ────────────────────────────────────────
    printf "${C_BOLD}Comandos disponibles${C_RESET}\n"
    printf "  Para ver el detalle de un comando: %s ${C_BRIGHT_MAGENTA}<cmd>${C_RESET} ${C_DIM}--help${C_RESET}\n" "$0"
    printf "\n"
    _box_draw 2 \
        "Comando"      "Descripción" \
        "start"        "Levantar containers (preserva volúmenes)" \
        "stop"         "Detener containers (preserva volúmenes)" \
        "restart"      "Reiniciar containers" \
        "up"           "build + start (recrea si cambió .env)" \
        "down"         "Detener y eliminar containers" \
        "build"        "Reconstruir imágenes Docker [--no-cache]" \
        "ps"           "Lista rápida de todos los containers" \
        "status"       "Estado por servicio (compose ps)" \
        "info"         "Imagen, comando, puertos, status" \
        "logs"         "Tail de logs (servicio REQUERIDO)" \
        "volumes"      "Volúmenes con tamaño real" \
        "exec"         "Ejecutar comando dentro de un container" \
        "setup"        "Primera instalación completa" \
        "bootstrap"    "Regenerar APP_KEY (idempotente)" \
        "db-backup"    "Exportar BD a db-backups/<name>.sql.gz" \
        "db-restore"   "Cargar dump con pre-backup safety net" \
        "seed-db"      "Migrate + (seeders y/o SQL dump)" \
        "sync-assets"  "Sincronizar brand-assets/ al FTP" \
        "nuke"         "down + eliminar volúmenes (BORRA DATOS)" \
        "rm-volumes"   "Eliminar volúmenes (-y salta confirmación)" \
        "fresh"        "down -v + rebuild + reinit + migrate + seed" \
        "hosts"        "Imprime el bloque para /etc/hosts" \
        "caddy"        "Imprime el Caddyfile a usar" \
        "help"         "Muestra esta ayuda (índice o detalle de un comando)"

    # ─── Destructivos (en rojo) ────────────────────────────────────
    _BOX_CMD_COLOR="${C_BRIGHT_RED}" _box_draw 4 \
        "Comando"      "Stage"    "Store"   "Descripción" \
        "nuke"         "dev|prod" "all"     "down + eliminar volúmenes (BORRA DATOS)" \
        "rm-volumes"   "dev|prod" "all"     "Eliminar volúmenes (-y salta confirmación)" \
        "fresh"        "dev|prod" "<store>" "down -v + rebuild + reinit + migrate + seed"

    # ─── Aliases ───────────────────────────────────────────────────
    printf "${C_BOLD}Aliases${C_RESET}\n"
    printf "  ${C_BRIGHT_MAGENTA}b${C_RESET}=build   ${C_BRIGHT_MAGENTA}rs${C_RESET}=restart   ${C_BRIGHT_MAGENTA}ls${C_RESET}=ps   ${C_BRIGHT_MAGENTA}psv${C_RESET}=info   ${C_BRIGHT_MAGENTA}vol${C_RESET}=volumes   ${C_BRIGHT_MAGENTA}rmvol${C_RESET}=rm-volumes\n"
    printf "\n"

    # ─── Puertos dev ───────────────────────────────────────────────
    # Resetear color de comando: la tabla de Puertos NO debe colorear
    # la columna "Tienda" como si fuera un comando.
    unset _BOX_CMD_COLOR
    printf "${C_BOLD}Puertos dev${C_RESET}  (shifted por instancia)\n"
    _box_draw 10 \
        "Tienda"                          "PG"  "Redis" "Backend" "Front" "FTP"  "PASV"         "pgAdm" "Insig" "FTPbr" \
        "panda-estampados-and-kitsune-dev" "6432" "7379"  "9000"    "5321"  "2121" "40000-40099" "6050"  "6540"  "9080" \
        "mm-boutique-dev"                  "7432" "8379"  "10000"   "6321"  "3121" "40100-40199" "7050"  "7540"  "10080" \
        "morales-store-dev"                "8432" "9379"  "11000"   "7321"  "4121" "40200-40299" "8050"  "8540"  "11080"
    printf "\n"

    # ─── Sub-tenants ───────────────────────────────────────────────
    printf "${C_BOLD}Sub-tenants${C_RESET}  (multi-tenancy: varias tiendas en una instancia)\n"
    printf "  panda-estampados-and-kitsune-dev hospeda 2 tenants:\n"
    printf "    pandas-estampados.com  (B2B mayorista)\n"
    printf "    kitsune.com              (B2C minorista)\n"
}

# _cmd_specific_help muestra el detalle de UN comando.
_cmd_specific_help() {
    local cmd="$1"
    local description="" usage_args="" example="" notes="" destructive=0

    case "$cmd" in
        start)
            description="Levantar containers (preserva volúmenes)."
            usage_args="<stage> <store|all> [<svc>]"
            example="Levantar todas las dev:    $0 start dev all
Levantar backend de Panda:    $0 start dev panda-estampados-and-kitsune backend"
            ;;
        stop)
            description="Detener containers (preserva volúmenes)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 stop dev all"
            ;;
        restart)
            description="Reiniciar containers sin rebuild."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 restart dev panda-estampados-and-kitsune backend"
            ;;
        up)
            description="Build + start. Recrea containers si .env cambió."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 up dev all
$0 up production panda-estampados-and-kitsune"
            ;;
        down)
            description="Detener y eliminar containers (preserva volúmenes)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 down dev all"
            ;;
        build)
            description="Reconstruir imágenes Docker."
            usage_args="[--no-cache] <stage> <store|all> [<svc>]"
            example="Con cache:    $0 build dev all
Sin cache:     $0 build --no-cache dev all
Solo backend:  $0 build dev panda-estampados-and-kitsune backend"
            notes="Sin --no-cache usa el cache de Docker."
            ;;
        ps)
            description="Vista rápida de TODAS las instancias en una sola tabla."
            usage_args="(sin args)"
            example="$0 ps"
            ;;
        status)
            description="Estado por servicio (compose ps)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 status dev all"
            ;;
        info)
            description="Imagen, comando, puertos, status (tabla detallada)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 info dev panda-estampados-and-kitsune"
            ;;
        logs)
            description="Tail de logs. Servicio REQUERIDO."
            usage_args="<stage> <store|all> <servicio>"
            example="$0 logs dev panda-estampados-and-kitsune backend"
            ;;
        volumes)
            description="Volúmenes Docker agrupados por categoría (data/cache/log) con tamaño real."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 volumes dev all"
            ;;
        exec)
            description="Ejecutar un comando dentro de un container."
            usage_args="<stage> <store> <servicio> <cmd...>"
            example="bash en backend:    $0 exec dev panda-estampados-and-kitsune backend bash
artisan tinker:    $0 exec dev panda-estampados-and-kitsune backend /usr/local/bin/php artisan tinker
psql:              $0 exec dev panda-estampados-and-kitsune postgres /usr/bin/psql -U postgres"
            ;;
        setup)
            description="Primera instalación completa: .env + levantar + bootstrap del backend."
            usage_args="<stage> <store|all>"
            example="$0 setup dev panda-estampados-and-kitsune"
            ;;
        bootstrap)
            description="Regenerar APP_KEY si falta o es placeholder (idempotente)."
            usage_args="<stage> <store>"
            example="$0 bootstrap dev panda-estampados-and-kitsune"
            notes="Para migrate + seed usar seed-db en su lugar."
            ;;
        db-backup)
            description="Exportar BD a db-backups/<name>.sql.gz."
            usage_args="<stage> <store>"
            example="$0 db-backup dev panda-estampados-and-kitsune"
            ;;
        db-restore)
            description="Cargar dump en BD. Pre-backup safety net automático."
            usage_args="<stage> <store> <archivo>"
            example="$0 db-restore dev panda-estampados-and-kitsune backup-2026-09-03.sql.gz"
            destructive=1
            ;;
        seed-db)
            description="Migrate + poblar BD con seeders y/o SQL dump."
            usage_args="<stage> <store> [--seed] [--sql <file>]"
            example="Solo migrate:      $0 seed-db dev panda-estampados-and-kitsune
Migrate + seeders:  $0 seed-db dev panda-estampados-and-kitsune --seed
Migrate + SQL:      $0 seed-db dev panda-estampados-and-kitsune --sql fixtures.sql"
            ;;
        sync-assets)
            description="Sincronizar brand-assets/ al FTP vía artisan."
            usage_args="<stage> <store|all>"
            example="$0 sync-assets dev all"
            ;;
        nuke)
            description="down + eliminar volúmenes."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 nuke dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        rm-volumes)
            description="Eliminar volúmenes Docker (-y salta confirmación)."
            usage_args="[-y] <stage> <store|all> [<svc>]"
            example="$0 rm-volumes -y dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        fresh)
            description="down -v + rebuild + reinit + migrate + seed."
            usage_args="<stage> <store>"
            example="$0 fresh dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        hosts)
            description="Imprime el bloque para /etc/hosts."
            usage_args="(sin args)"
            example="$0 hosts | sudo tee -a /etc/hosts"
            ;;
        caddy)
            description="Imprime el Caddyfile a usar."
            usage_args="(sin args)"
            example="$0 caddy | sudo tee /etc/caddy/Caddyfile && sudo systemctl restart caddy"
            ;;
        help)
            cmd_help
            return 0
            ;;
        *)
            printf "${C_BRIGHT_RED}Comando desconocido:${C_RESET} %s\n\n" "$cmd"
            printf "Comandos válidos: %s ${C_BRIGHT_MAGENTA}help${C_RESET} para ver el índice\n" "$0"
            return 1
            ;;
    esac

    # ─── Render del help específico ───────────────────────────────
    local cmd_color="${C_BRIGHT_MAGENTA}"
    if (( destructive == 1 )); then
        cmd_color="${C_BRIGHT_RED}"
    fi

    printf "\n"
    printf "${cmd_color}%s${C_RESET}  %s\n" "$cmd" "$description"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${cmd_color}%s${C_RESET} %s\n" "$0" "$cmd" "$usage_args"
    printf "\n"
    printf "${C_BOLD}Ejemplos${C_RESET}\n"
    if [[ -n "$example" ]]; then
        while IFS= read -r line; do
            printf "  ${C_DIM}%s${C_RESET}\n" "$line"
        done <<< "$example"
    fi
    if [[ -n "$notes" ]]; then
        printf "\n"
        printf "${C_BOLD}Notas${C_RESET}\n"
        printf "  ${C_DIM}%s${C_RESET}\n" "$notes"
    fi
    if (( destructive == 1 )); then
        printf "\n"
        printf "${C_BRIGHT_RED}Comando destructivo:${C_RESET} borra datos sin rollback automatico.\n"
    fi
    printf "\n"
}

# ─────────────────────────────────────────────────────────────────
# Helpers de formato.
# ─────────────────────────────────────────────────────────────────

# _ansi_strip quita códigos ANSI del stdin (lee del pipe, no del here-string).
# Esencial para que el cálculo de anchos de columna NO cuente los bytes
# de escape ANSI cuando el caller pasa un valor coloreado.
_ansi_strip() {
    /usr/bin/sed -r "s/\x1B\[[0-9;]*[A-Za-z]//g"
}

# _visible_len devuelve la longitud VISIBLE de un string en CHARS
# (no bytes). Sin códigos ANSI, "ó" cuenta como 1 char; con wc -c
# contaría 2 bytes (UTF-8) y desalinearía las tablas con texto en
# español. Usamos awk que cuenta chars por carácter, no bytes.
_visible_len() {
    printf '%s' "$1" | _ansi_strip | /usr/bin/awk '{ print length }'
}

# _box_draw dibuja una tabla Markdown-style (|---|---|) con bordes
# generados por printf (no csvlook, porque csvlook no soporta ANSI
# en celdas — contaría los bytes de escape y desalinearía).
#
# Ancho de cada columna = max(len_visible(header), max(len_visible(value))).
# Los códigos ANSI en los valores se cuentan solo como su longitud
# visible para el cálculo de anchos; al imprimir, se emiten enteros.
#
# La primera columna (índice 0) se pinta con $_BOX_CMD_COLOR si está
# definida (magenta por default). Para tablas destructivas, setear
# _BOX_CMD_COLOR="${C_BRIGHT_RED}" antes de llamar.
#
# Args: n_col  header1 header2 ... headerN  data1_1 ... dataN_M
#
# Ejemplo:
#   _box_draw 3 "Cmd" "Stage" "Desc" \
#             "start" "dev" "Levantar" \
#             "stop"  "dev" "Detener"
_box_draw() {
    local n_cols="${1:-0}"
    shift
    local total_args=${#@}
    if (( n_cols <= 0 )) || (( total_args == 0 )); then
        return 0
    fi
    if (( total_args % n_cols != 0 )); then
        log_err "_box_draw: args ($total_args) no múltiplo de cols ($n_cols)"
        return 1
    fi

    local -a all_args=("$@")
    local -a headers=("${all_args[@]:0:n_cols}")
    local -a rows=("${all_args[@]:n_cols}")

    local cmd_color="${_BOX_CMD_COLOR:-${C_BRIGHT_MAGENTA}}"

    # Calcular anchos por columna basándose en la longitud VISIBLE.
    local -a widths
    local c r vw
    for ((c=0; c<n_cols; c++)); do
        local w
        w=$(_visible_len "${headers[c]}")
        for ((r=c; r<${#rows[@]}; r+=n_cols)); do
            vw=$(_visible_len "${rows[r]}")
            (( vw > w )) && w=$vw
        done
        widths+=("$w")
    done

    # Construir bordes.
    #   top:    +-------+-------+-------+
    #   mid:    +-------+-------+-------+     (separador entre header y datos)
    #   bot:    +-------+-------+-------+
    local top_border="" mid_border="" bot_border=""
    for ((c=0; c<n_cols; c++)); do
        local seg
        seg=$(printf -- '-%.0s' $(/usr/bin/seq 1 $((widths[c]+2))))
        top_border+="+${seg}"
        mid_border+="+${seg}"
        bot_border+="+${seg}"
    done
    top_border="${top_border}+"
    mid_border="${mid_border}+"
    bot_border="${bot_border}+"

    # Top.
    printf "%s\n" "$top_border"

    # Header.
    printf "|"
    for ((c=0; c<n_cols; c++)); do
        printf " ${C_BOLD}%-${widths[c]}s${C_RESET} |" "${headers[c]}"
    done
    printf "\n"

    # Mid (separator).
    printf "%s\n" "$mid_border"

    # Filas.
    local n_rows=$(( ${#rows[@]} / n_cols ))
    local off
    for ((r=0; r<n_rows; r++)); do
        off=$((r * n_cols))
        printf "|"
        # Columna 0 con color.
        printf " ${cmd_color}%-${widths[0]}s${C_RESET} |" "${rows[off]}"
        # Resto sin color.
        for ((c=1; c<n_cols; c++)); do
            printf " %-${widths[c]}s |" "${rows[off+c]}"
        done
        printf "\n"
    done

    # Bottom.
    printf "%s\n" "$bot_border"
    printf "\n"
}
