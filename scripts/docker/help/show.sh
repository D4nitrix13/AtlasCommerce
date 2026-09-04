#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help [comando]
#
# Pantalla de ayuda del orquestador Docker.
#
# Formas de uso:
#   ./scripts/docker-stack.sh help           -> índice compacto de todos los comandos
#   ./scripts/docker-stack.sh help <comando> -> ayuda detallada de un comando específico
#   ./scripts/docker-stack.sh <cmd> --help   -> atajo del anterior
#
# Implementación: tablas Markdown-style (|---|) generadas por `csvlook`.
# Sin emojis, sin "(color X en ejemplos)", sin ruido.

# Ruta absoluta a csvlook (instalado vía brew en linuxbrew).
_CSVLOOK="/home/linuxbrew/.linuxbrew/bin/csvlook"
# Fallback a `column` de util-linux si csvlook no está disponible.
_COLUMN_FMT="/usr/bin/column -t -s '|'"

cmd_help() {
    local subcmd="${1:-}"

    # Si nos piden ayuda de un comando específico, despachar.
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
    _box_csv 2 \
        "Comando"      "Descripción" \
        "start"        "Levantar containers (preserva volúmenes)" \
        "stop"         "Detener containers (preserva volúmenes)" \
        "restart"      "Reiniciar containers" \
        "up"          "build + start (recrea si cambió .env)" \
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

    # ─── Aliases ───────────────────────────────────────────────────
    printf "${C_BOLD}Aliases${C_RESET}\n"
    printf "  ${C_BRIGHT_MAGENTA}b${C_RESET}=build   ${C_BRIGHT_MAGENTA}rs${C_RESET}=restart   ${C_BRIGHT_MAGENTA}ls${C_RESET}=ps   ${C_BRIGHT_MAGENTA}psv${C_RESET}=info   ${C_BRIGHT_MAGENTA}vol${C_RESET}=volumes   ${C_BRIGHT_MAGENTA}rmvol${C_RESET}=rm-volumes\n"
    printf "\n"

    # ─── Puertos dev ───────────────────────────────────────────────
    printf "${C_BOLD}Puertos dev${C_RESET}  (shifted por instancia)\n"
    _box_csv 10 \
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
    printf "\n"

    # ─── Recordatorio destructivo ──────────────────────────────────
    printf "${C_BRIGHT_RED}Comandos destructivos (borran datos)${C_RESET}: ${C_BRIGHT_MAGENTA}nuke${C_RESET} · ${C_BRIGHT_MAGENTA}rm-volumes${C_RESET} · ${C_BRIGHT_MAGENTA}fresh${C_RESET}\n"
    printf "  Detalle: %s ${C_BRIGHT_MAGENTA}<cmd>${C_RESET} ${C_DIM}--help${C_RESET}\n" "$0"
}

# _cmd_specific_help muestra el detalle de UN comando. Se invoca como
#   cmd_help <comando>
# Caso vacío o "help" → delega al índice raíz (sin args).
_cmd_specific_help() {
    local cmd="$1"
    local usage_args="" description="" example="" notes="" destructive=0

    case "$cmd" in
        # ─── Ciclo de vida ─────────────────────────────────────────
        start)
            description="Levantar containers (preserva volúmenes)."
            usage_args="<stage> <store|all> [<svc>]"
            example="Levantar todas las dev:    $0 start dev all
Levantar backend de Panda:    $0 start dev panda-estampados-and-kitsune backend"
            ;;
        stop)
            description="Detener containers (preserva volúmenes)."
            usage_args="<stage> <store|all> [<svc>]"
            example="Detener todas las dev:    $0 stop dev all
Detener backend de MM:       $0 stop dev mm-boutique backend"
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
        # ─── Build ─────────────────────────────────────────────────
        build)
            description="Reconstruir imágenes Docker."
            usage_args="[--no-cache] <stage> <store|all> [<svc>]"
            example="Con cache (rápido):  $0 build dev all
Sin cache (limpio):   $0 build --no-cache dev all
Solo backend:        $0 build dev panda-estampados-and-kitsune backend"
            notes="Sin --no-cache usa el cache de Docker. Usar --no-cache cuando hay cambios en Dockerfiles o layers base."
            ;;
        # ─── Diagnóstico ───────────────────────────────────────────
        ps)
            description="Vista rápida de todos los containers (todas las instancias)."
            usage_args="(sin args)"
            example="$0 ps"
            ;;
        status)
            description="Estado por servicio (compose ps)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 status dev all
$0 status production panda-estampados-and-kitsune"
            ;;
        info)
            description="Imagen, comando, puertos, status (tabla detallada)."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 info dev panda-estampados-and-kitsune"
            ;;
        logs)
            description="Tail de logs. Servicio REQUERIDO."
            usage_args="<stage> <store|all> <servicio>"
            example="Backend de Panda:   $0 logs dev panda-estampados-and-kitsune backend
Postgres prod:      $0 logs production panda-estampados-and-kitsune postgres"
            ;;
        volumes)
            description="Volúmenes Docker agrupados por categoría (data/cache/log) con tamaño real."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 volumes dev all"
            ;;
        # ─── Ejecución ─────────────────────────────────────────────
        exec)
            description="Ejecutar un comando dentro de un container."
            usage_args="<stage> <store> <servicio> <cmd...>"
            example="bash en backend:        $0 exec dev panda-estampados-and-kitsune backend bash
artisan tinker:        $0 exec dev panda-estampados-and-kitsune backend /usr/local/bin/php artisan tinker
psql:                  $0 exec dev panda-estampados-and-kitsune postgres /usr/bin/psql -U postgres"
            ;;
        # ─── Inicialización ─────────────────────────────────────────
        setup)
            description="Primera instalación completa: copia .env, levanta containers, corre bootstrap del backend (storage:link + caches + migrate + tenants:bootstrap + key:generate)."
            usage_args="<stage> <store|all>"
            example="Setup inicial de Panda dev:  $0 setup dev panda-estampados-and-kitsune
Setup de todas las dev:       $0 setup dev all"
            notes="Equivale a: ensure_env_file + docker compose up -d + bootstrap. Usar solo la primera vez o después de nuke."
            ;;
        bootstrap)
            description="Regenerar APP_KEY si falta o es placeholder (idempotente, no migra ni siembra)."
            usage_args="<stage> <store>"
            example="$0 bootstrap dev panda-estampados-and-kitsune"
            notes="Si necesitás migrate + seed, usá setup en su lugar (primera instalación) o seed-db (datos)."
            ;;
        # ─── Base de datos ─────────────────────────────────────────
        db-backup)
            description="Exportar BD a db-backups/<name>.sql.gz. Pre-backup safety net automático."
            usage_args="<stage> <store>"
            example="$0 db-backup dev panda-estampados-and-kitsune"
            ;;
        db-restore)
            description="Cargar dump en BD. Hace pre-backup automático antes de pisar."
            usage_args="<stage> <store> <archivo>"
            example="$0 db-restore dev panda-estampados-and-kitsune backup-2026-09-03.sql.gz"
            destructive=1
            ;;
        seed-db)
            description="Migrate + poblar BD con seeders y/o SQL dump."
            usage_args="<stage> <store> [--seed] [--sql <archivo>]"
            example="Solo migrate:        $0 seed-db dev panda-estampados-and-kitsune
Migrate + seeders:    $0 seed-db dev panda-estampados-and-kitsune --seed
Migrate + SQL dump:   $0 seed-db dev panda-estampados-and-kitsune --sql fixtures.sql"
            ;;
        # ─── Assets ────────────────────────────────────────────────
        sync-assets)
            description="Sincronizar brand-assets/ al FTP vía artisan (php artisan assets:sync)."
            usage_args="<stage> <store|all>"
            example="$0 sync-assets dev all
$0 sync-assets production panda-estampados-and-kitsune"
            ;;
        # ─── Destructivos ──────────────────────────────────────────
        nuke)
            description="down + eliminar volúmenes. BORRA DATOS."
            usage_args="<stage> <store|all> [<svc>]"
            example="$0 nuke dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        rm-volumes)
            description="Eliminar volúmenes Docker (-y salta confirmación)."
            usage_args="[-y] <stage> <store|all> [<svc>]"
            example="Con confirmacion:   $0 rm-volumes dev all
Sin confirmacion:    $0 rm-volumes -y dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        fresh)
            description="down -v + rebuild + reinit + migrate + seed. BORRA TODO Y RECREA."
            usage_args="<stage> <store>"
            example="$0 fresh dev panda-estampados-and-kitsune"
            destructive=1
            ;;
        # ─── Utilidades ─────────────────────────────────────────────
        hosts)
            description="Imprime el bloque para /etc/hosts (necesario para que los dominios .test/.com resuelvan localmente)."
            usage_args="(sin args)"
            example="$0 hosts | sudo tee -a /etc/hosts"
            ;;
        caddy)
            description="Imprime el Caddyfile a usar."
            usage_args="(sin args)"
            example="$0 caddy | sudo tee /etc/caddy/Caddyfile && sudo systemctl restart caddy"
            ;;
        help)
            # Si alguien hace `help help`, mostramos el índice.
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
    local color_desc="${C_BRIGHT_WHITE}"
    if (( destructive == 1 )); then
        color_desc="${C_BRIGHT_RED}"
    fi

    printf "\n"
    printf "${C_BOLD}%s${C_RESET}  ${color_desc}%s${C_RESET}\n" "$cmd" "$description"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${C_BRIGHT_MAGENTA}%s${C_RESET} %s\n" "$0" "$cmd" "$usage_args"
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
        printf "${C_BRIGHT_RED}Comando destructivo:${C_RESET} borra datos sin posibilidad de rollback automatico.\n"
    fi
    printf "\n"
}

# ─────────────────────────────────────────────────────────────────
# Helpers de formato.
# ─────────────────────────────────────────────────────────────────

# _box_csv dibuja una tabla con bordes Markdown-style (|---|) usando
# `csvlook` (de csvkit). Si csvlook no está disponible, hace fallback
# a `column -t -s '|'` (util-linux).
#
# Args: n_col  header1 header2 ... headerN  data1_1 ... dataN_M
#
# Ejemplo:
#   _box_csv 3 "Cmd" "Stage" "Desc" \
#           "start" "dev" "Levantar" \
#           "stop"  "dev" "Detener"
_box_csv() {
    local n_cols="${1:-0}"
    shift
    local total_args=${#@}
    if (( n_cols <= 0 )) || (( total_args == 0 )); then
        return 0
    fi
    if (( total_args % n_cols != 0 )); then
        log_err "_box_csv: args ($total_args) no múltiplo de cols ($n_cols)"
        return 1
    fi

    # Generar CSV. Escapar comas en valores con comillas dobles (CSV estándar).
    local -a all_args=("$@")
    local -a headers=("${all_args[@]:0:n_cols}")
    local -a rows=("${all_args[@]:n_cols}")

    local csv=""
    local IFS=','
    csv="${headers[*]}"
    local n_rows=$(( ${#rows[@]} / n_cols ))
    for ((r=0; r<n_rows; r++)); do
        local off=$((r * n_cols))
        local row=""
        for ((c=0; c<n_cols; c++)); do
            if (( c > 0 )); then
                row+=","
            fi
            local v="${rows[off+c]}"
            if [[ "$v" == *,* || "$v" == *'"'* || "$v" == *$'\n'* ]]; then
                v="${v//\"/\"\"}"
                v="\"$v\""
            fi
            row+="$v"
        done
        csv+=$'\n'"$row"
    done
    IFS=$' \t\n'

    if [[ -x "$_CSVLOOK" ]]; then
        printf '%s\n' "$csv" | "$_CSVLOOK"
    elif /usr/bin/command -v column >/dev/null 2>&1; then
        printf '%s\n' "$csv" | $_COLUMN_FMT
    else
        printf '%s\n' "$csv"
    fi
    printf "\n"
}
