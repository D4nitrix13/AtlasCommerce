#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker-stack.sh — gestor de las 4 instancias Docker
# ------------------------------------------------------------
# Levanta, detiene, reinicia, lista, loguea y ejecuta comandos
# dentro de las instancias configuradas en docker/instances/<nombre>/
# y del dev compose en docker/dev/.
#
# Uso:
#   ./scripts/docker-stack.sh <comando> [args...]
#
# Comandos (aceptan [instancia|all] y, casi todos, [servicio] opcional):
#   up      [instancia|all] [svc] build + start (convenio docker compose)
#   start   [instancia|all] [svc] Levantar containers (preserva volúmenes)
#   stop    [instancia|all] [svc] Detener containers (preserva volúmenes)
#   restart [instancia|all] [svc] Reiniciar containers
#   build   [instancia|all] [svc] Reconstruir imágenes (--no-cache)
#   down    [instancia|all] [svc] Detener y eliminar containers (preserva volúmenes)
#   nuke    [instancia|all] [svc] down + eliminar volúmenes (BORRA DATOS)
#   status  [instancia|all] [svc] Estado de cada servicio
#   ps                            Lista de containers (vista rápida)
#   info / psv                    Tabla detallada: instancia, servicio,
#                                  estado, puertos y comando del container.
#                                  [instancia|all] [svc] opcional.
#   logs    [instancia] [svc]    Tail de logs (default: todos)
#   exec    <instancia> <svc> <cmd...>
#                                Ejecuta <cmd> dentro del container
#   setup   [prod|dev|all]       Primera vez: copia .env.example, levanta
#                                (default: all)
#   hosts                        Imprime el bloque para /etc/hosts
#   caddy                        Imprime el Caddyfile a usar
#   help                         Muestra esta ayuda
#
# Aliases cortos: b=build, rs=restart, ls=ps, psv=info.
#
# Instancias válidas (todas con scope `production:` o `development:`):
#   production:panda-and-kitsune  (multi-tenant: Panda + Kitsune)
#   production:mm-boutique        (single-tenant)
#   production:morales-store      (single-tenant)
#   development:panda-and-kitsune (entorno local, BD aislada, bind mounts + xdebug)
#   development:mm-boutique       (entorno local, BD aislada, bind mounts + xdebug)
#   development:morales-store     (entorno local, BD aislada, bind mounts + xdebug)
#   all                           = las 6 (3 production + 3 development)
#
# Sin scope, default = production. Equivalencias:
#   start panda-and-kitsune       → start production:panda-and-kitsune
#   start development:panda-and-kitsune → start dev:panda-and-kitsune
#
# Requiere: docker compose v2 (>= 5.x), bash 4+.
# ------------------------------------------------------------
set -euo pipefail

# --- Constantes ------------------------------------------------------------

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INSTANCES_DIR="${ROOT_DIR}/docker/instances"
DEV_DIR="${ROOT_DIR}/docker/dev"

# Las instancias se identifican con un scope: `production:<store>` o
# `development:<store>`. Sin scope, se asume `production:` (default).
# Esto permite que el mismo nombre de tienda exista en prod y dev
# (panda-and-kitsune es el multi-tenant de Pandas Estampados + Kitsune
# en ambos mundos, pero vive en carpetas distintas y con compose /
# Dockerfiles distintos).
PROD_INSTANCES=(production:panda-and-kitsune production:mm-boutique production:morales-store)
DEV_INSTANCES=(development:panda-and-kitsune development:mm-boutique development:morales-store)
ALL_INSTANCES=("${PROD_INSTANCES[@]}" "${DEV_INSTANCES[@]}")

# Color helpers (TTY-aware)
if [[ -t 1 ]]; then
    C_RESET=$'\033[0m'
    C_BOLD=$'\033[1m'
    C_RED=$'\033[31m'
    C_GREEN=$'\033[32m'
    C_YELLOW=$'\033[33m'
    C_BLUE=$'\033[34m'
    C_CYAN=$'\033[36m'
else
    C_RESET=""; C_BOLD=""; C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_CYAN=""
fi

# --- Funciones -------------------------------------------------------------

log_info()  { printf "${C_BLUE}[info]${C_RESET}  %s\n" "$*"; }
log_ok()    { printf "${C_GREEN}[ok]${C_RESET}    %s\n" "$*"; }
log_warn()  { printf "${C_YELLOW}[warn]${C_RESET}  %s\n" "$*"; }
log_err()   { printf "${C_RED}[error]${C_RESET} %s\n" "$*" >&2; }
log_bold()  { printf "${C_BOLD}%s${C_RESET}\n" "$*"; }

usage() {
    sed -n '3,38p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

# is_valid_instance <name>
is_valid_instance() {
    local name="$1"
    for inst in "${ALL_INSTANCES[@]}"; do
        [[ "$inst" == "$name" ]] && return 0
    done
    return 1
}

# instance_compose_dir <name>
# Resuelve la ruta al directorio de la instancia según el scope.
# Scope explícito (production:<store> / development:<store>) tiene
# prioridad. Sin scope, default = production.
instance_compose_dir() {
    local name="$1"
    case "$name" in
        production:*) echo "${INSTANCES_DIR}/production/${name#production:}" ;;
        development:*) echo "${INSTANCES_DIR}/development/${name#development:}" ;;
        *)
            # Sin scope: default production.
            echo "${INSTANCES_DIR}/production/${name}"
            ;;
    esac
}

# instance_compose_file <name>
# Todas las instancias usan `docker-compose.yml` en su carpeta.
instance_compose_file() {
    local name="$1"
    echo "docker-compose.yml"
}

# instance_env_file <name>
# Cada instancia tiene su .env en environment/ dentro de su carpeta.
instance_env_file() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ -f "${dir}/environment/.env" ]]; then
        echo "--env-file ${dir}/environment/.env"
    else
        echo ""
    fi
}

# resolve_targets <stage|instancia> [servicio]
# Variante de resolve_instances que también captura un servicio
# opcional. Imprime líneas "<instancia>\t<servicio>". Si servicio
# está vacío, opera sobre todo el stack. Esto permite que cada
# comando (start, stop, build, restart, down, nuke) opere a nivel
# de stack completo o de un servicio individual.
#
# Scopes soportados:
#   all                  → todas las instancias (3 prod + 3 dev)
#   production / prod    → todas las instancias de production
#   development / dev    → todas las instancias de development
#   production:<store>   → 1 instancia de prod con scope explícito
#   development:<store>  → 1 instancia de dev con scope explícito
#   <store>              → 1 instancia, default = production
#
# Ejemplos:
#   resolve_targets all                  → 6 instancias
#   resolve_targets development          → 3 dev
#   resolve_targets dev backend          → 3 dev con servicio backend
#   resolve_targets production           → 3 prod
#   resolve_targets development:mm-boutique → 1 dev específica
#   resolve_targets mm-boutique          → 1 prod (default)
resolve_targets() {
    local arg="${1:-all}"
    local svc="${2:-}"
    if [[ -z "$arg" ]]; then
        log_err "Falta la instancia o stage. Usá 'all', 'development', 'production', o una de: ${ALL_INSTANCES[*]}"
        exit 1
    fi
    case "$arg" in
        all)
            for inst in "${ALL_INSTANCES[@]}"; do
                printf "%s\t%s\n" "$inst" "$svc"
            done
            return
            ;;
        production|prod)
            for inst in "${PROD_INSTANCES[@]}"; do
                printf "%s\t%s\n" "$inst" "$svc"
            done
            return
            ;;
        development|dev)
            # Cada tienda dev usa puertos shifted distintos para que las
            # 3 puedan correr en paralelo sin conflicto:
            #   panda-and-kitsune-dev: 6432/7379/9000/5321/2121 (+1000)
            #   mm-boutique-dev:       7432/8379/10000/6321/3121 (+2000)
            #   morals-store-dev:     8432/9379/11000/7321/4121 (+3000)
            # Ver docker/instances/development/<store>/environment/.env
            for inst in "${DEV_INSTANCES[@]}"; do
                printf "%s\t%s\n" "$inst" "$svc"
            done
            return
            ;;
    esac
    if is_valid_instance "$arg"; then
        printf "%s\t%s\n" "$arg" "$svc"
        return
    fi
    log_err "Instancia o stage desconocido: $arg. Válidas: all, production|prod, development|dev, o: ${ALL_INSTANCES[*]}"
    exit 1
}

# resolve_instances <arg>
# Deprecated: mantener por retrocompatibilidad interna (cmd_setup).
# Devuelve solo el nombre de instancia, una por línea.
resolve_instances() {
    local arg="${1:-}"
    if [[ -z "$arg" ]]; then
        log_err "Falta la instancia. Usá 'all' o una de: ${ALL_INSTANCES[*]}"
        exit 1
    fi
    if [[ "$arg" == "all" ]]; then
        printf "%s\n" "${ALL_INSTANCES[@]}"
        return
    fi
    if is_valid_instance "$arg"; then
        echo "$arg"
        return
    fi
    log_err "Instancia desconocida: $arg. Válidas: ${ALL_INSTANCES[*]}"
    exit 1
}

# ensure_env_file <instance>
# Si la instancia no tiene environment/.env (solo .env.example), lo copia y avisa.
ensure_env_file() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ ! -f "${dir}/environment/.env" ]]; then
        if [[ -f "${dir}/environment/.env.example" ]]; then
            log_warn "${name}: environment/.env no existe — copiando .env.example (editá los secretos antes de producción)."
            cp "${dir}/environment/.env.example" "${dir}/environment/.env"
            chmod 644 "${dir}/environment/.env"
        else
            log_err "${name}: no hay environment/.env ni environment/.env.example en ${dir}"
            exit 1
        fi
    fi
}

# docker_compose <instance> <args...>
docker_compose() {
    local name="$1"; shift
    local dir
    dir="$(instance_compose_dir "$name")"
    local file
    file="$(instance_compose_file "$name")"
    local env_file
    env_file="$(instance_env_file "$name")"

    # shellcheck disable=SC2086
    (cd "$dir" && docker compose -f "$file" $env_file "$@")
}

# --- Comandos --------------------------------------------------------------

cmd_up() {
    # Equivalente a `docker compose up -d --build`: rebuildea las
    # imágenes que cambiaron y levanta los containers en background.
    # Convención de docker compose: `up` = build + start. Útil
    # cuando uno quiere iterar sobre cambios en un Dockerfile
    # sin tener que correr `build` y `start` por separado.
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Building + levantando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans "$svc"
            log_ok "${inst}/${svc} arriba."
        else
            log_info "Building + levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --build --remove-orphans
            log_ok "${inst} arriba."
        fi
    done < <(resolve_targets "$@")
}

cmd_start() {
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Levantando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans "$svc"
            log_ok "${inst}/${svc} levantado."
        else
            log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" up -d --remove-orphans
            log_ok "${inst} levantado."
        fi
    done < <(resolve_targets "$@")
}

cmd_stop() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Deteniendo ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" stop "$svc"
            log_ok "${inst}/${svc} detenido (container preservado)."
        else
            log_info "Deteniendo ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" stop
            log_ok "${inst} detenido (containers preservados)."
        fi
    done < <(resolve_targets "$@")
}

cmd_restart() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Reiniciando ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" restart "$svc"
            log_ok "${inst}/${svc} reiniciado."
        else
            ensure_env_file "$inst"
            log_info "Reiniciando ${C_BOLD}${inst}${C_RESET}…"
            docker_compose "$inst" restart
            log_ok "${inst} reiniciado."
        fi
    done < <(resolve_targets "$@")
}

cmd_down() {
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Bajando container ${C_BOLD}${inst}/${svc}${C_RESET}…"
            docker_compose "$inst" rm -f -s "$svc"
            log_ok "${inst}/${svc} bajado."
        else
            log_info "Bajando containers de ${C_BOLD}${inst}${C_RESET} (preserva volúmenes)…"
            docker_compose "$inst" down --remove-orphans
            log_ok "${inst} abajo."
        fi
    done < <(resolve_targets "$@")
}

cmd_nuke() {
    log_warn "⚠  nuke ELIMINA VOLÚMENES. Esto borra la base de datos completa."
    read -rp "¿Confirmás? escribí 'yes' para continuar: " confirm
    [[ "$confirm" == "yes" ]] || { log_info "Cancelado."; exit 0; }
    while IFS=$'\t' read -r inst svc; do
        if [[ -n "$svc" ]]; then
            log_info "Nuke de ${C_BOLD}${inst}/${svc}${C_RESET} (container + volumen)…"
            docker_compose "$inst" rm -f -s -v "$svc"
            log_ok "${inst}/${svc} nuked."
        else
            log_info "Nuke de ${C_BOLD}${inst}${C_RESET} (containers + volúmenes)…"
            docker_compose "$inst" down --volumes --remove-orphans
            log_ok "${inst} nuked."
        fi
    done < <(resolve_targets "$@")
}

cmd_status() {
    while IFS=$'\t' read -r inst svc; do
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        if [[ -n "$svc" ]]; then
            docker_compose "$inst" ps "$svc"
        else
            docker_compose "$inst" ps
        fi
    done < <(resolve_targets "$@")
}

cmd_ps() {
    docker ps --filter "label=com.docker.compose.project" \
        --format "table {{.Label \"com.docker.compose.project\"}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"
}

# Tabla detallada con instancia, servicio, estado, puertos y comando
# del container. Útil para diagnóstico rápido: a simple vista se ve
# qué container de qué instancia está corriendo qué binario y qué
# puertos tiene publicados al host.
#
# Uso:
#   info            # todas las instancias y servicios
#   info dev        # solo dev
#   info dev backend # solo un servicio
cmd_info() {
    # docker ps --filter label=foo solo soporta AND, no OR entre
    # instancias. Iteramos por fuera y emitimos un único header
    # al inicio (con `tail -n +2` saltamos el header de las
    # llamadas siguientes).
    local header_printed=0
    local format_row='{{.Label "com.docker.compose.project"}}\t{{.Label "com.docker.compose.service"}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}\t{{.Command}}'
    while IFS=$'\t' read -r inst svc; do
        local filter_args=(--filter "label=com.docker.compose.project=$inst")
        [[ -n "$svc" ]] && filter_args+=(--filter "label=com.docker.compose.service=$svc")
        if [[ $header_printed -eq 0 ]]; then
            docker ps -a "${filter_args[@]}" --format "table $format_row"
            header_printed=1
        else
            docker ps -a "${filter_args[@]}" --format "$format_row" | tail -n +2
        fi
    done < <(resolve_targets "$@")
}

cmd_logs() {
    local arg="${1:-all}"; shift || true
    if [[ "${arg}" == "all" || -z "${arg}" ]]; then
        log_info "Logs de ${C_BOLD}todas las instancias${C_RESET} (Ctrl+C para salir)…"
        # Compose logs no soporta múltiples -f, usamos docker compose logs
        # en cada uno y mergeamos con labels.
        for inst in "${ALL_INSTANCES[@]}"; do
            if docker_compose "$inst" ps --services 2>/dev/null | grep -q .; then
                docker_compose "$inst" logs -f --tail=50 "$@" 2>&1 | sed "s|^|[$inst] |" &
            fi
        done
        wait
    else
        docker_compose "$arg" logs -f --tail=100 "$@"
    fi
}

cmd_exec() {
    if [[ $# -lt 3 ]]; then
        log_err "exec requiere: <instancia> <servicio> <comando...>"
        exit 1
    fi
    local inst="$1"; shift
    local svc="$1"; shift
    is_valid_instance "$inst" || { log_err "Instancia desconocida: $inst"; exit 1; }
    log_info "Ejecutando en ${C_BOLD}${inst}/${svc}${C_RESET}: $*"
    docker_compose "$inst" exec "$svc" "$@"
}

cmd_build() {
    while IFS=$'\t' read -r inst svc; do
        ensure_env_file "$inst"
        if [[ -n "$svc" ]]; then
            log_info "Building ${C_BOLD}${inst}/${svc}${C_RESET} (no cache)…"
            docker_compose "$inst" build --no-cache "$svc"
            log_ok "${inst}/${svc} construido."
        else
            log_info "Building ${C_BOLD}${inst}${C_RESET} (no cache)…"
            docker_compose "$inst" build --no-cache
            log_ok "${inst} construido."
        fi
    done < <(resolve_targets "$@")
}

cmd_setup() {
    local scope="${1:-all}"
    log_bold "Primera instalación de las instancias."
    # 1. docker/versions.env (centraliza versiones de imágenes)
    if [[ ! -f "${ROOT_DIR}/docker/versions.env" ]]; then
        if [[ -f "${ROOT_DIR}/docker/versions.env.example" ]]; then
            log_info "Creando docker/versions.env desde .example (editá versiones si querés pinear)."
            cp "${ROOT_DIR}/docker/versions.env.example" "${ROOT_DIR}/docker/versions.env"
        else
            log_err "docker/versions.env.example no existe. Repo corrupto?"
            exit 1
        fi
    fi
    # 2. Resolver qué instancias levanta este setup según el scope.
    local -a instances_to_start=()
    case "$scope" in
        prod)
            instances_to_start=("${PROD_INSTANCES[@]}")
            log_info "Scope: prod (${PROD_INSTANCES[*]})"
            ;;
        dev)
            instances_to_start=("${DEV_INSTANCES[@]}")
            log_info "Scope: dev (${DEV_INSTANCES[*]})"
            ;;
        all)
            instances_to_start=("${ALL_INSTANCES[@]}")
            log_info "Scope: all (prod + dev)"
            ;;
        *)
            log_err "Scope inválido: $scope. Usá: prod | dev | all"
            exit 1
            ;;
    esac
    # 3. Asegurar .env de cada instancia (para dev, ensure_env_file es no-op;
    #    el chequeo real se hace abajo).
    for inst in "${instances_to_start[@]}"; do
        ensure_env_file "$inst"
    done
    # 4. Levantar.
    for inst in "${instances_to_start[@]}"; do
        if [[ "$inst" == "dev" && ! -f "${DEV_DIR}/.env" ]]; then
            log_warn "dev: .env no existe, omitido. Copiá docker/dev/.env.example cuando lo necesites."
            continue
        fi
        log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" up -d --remove-orphans
        log_ok "${inst} levantado."
    done
    log_ok "Setup completo. Próximos pasos:"
    echo "  1. sudo ./scripts/docker-stack.sh hosts >>/etc/hosts"
    echo "  2. sudo cp Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy"
    echo "  3. Probar: curl -sI http://pandas-estampados.com/api/storefront/theme"
}

cmd_hosts() {
    cat <<'EOF'
# Pegar este bloque al final de /etc/hosts (con sudo):
127.0.0.1  pandas-estampados.com
127.0.0.1  www.pandas-estampados.com
127.0.0.1  admin.pandas-estampados.com
127.0.0.1  kitsune.com
127.0.0.1  www.kitsune.com
127.0.0.1  admin.kitsune.com
127.0.0.1  mm-boutique.com
127.0.0.1  www.mm-boutique.com
127.0.0.1  admin.mm-boutique.com
127.0.0.1  morales-store.com
127.0.0.1  www.morales-store.com
127.0.0.1  admin.morales-store.com
EOF
}

cmd_caddy() {
    cat "${ROOT_DIR}/Caddyfile"
}

cmd_help() {
    usage
    cat <<EOF

Ejemplos:
  ${C_CYAN}$0 start all${C_RESET}                       # levanta las 6 instancias (3 prod + 3 dev)
  ${C_CYAN}$0 start production${C_RESET}                # levanta las 3 prod
  ${C_CYAN}$0 start development${C_RESET}               # levanta SOLO panda-and-kitsune-dev (la 1° dev)
  ${C_CYAN}$0 start dev${C_RESET}                       # atajo de development
  ${C_CYAN}$0 start production:mm-boutique${C_RESET}    # levanta solo MM Boutique prod
  ${C_CYAN}$0 start development:mm-boutique${C_RESET}   # levanta solo MM Boutique dev
  ${C_CYAN}$0 stop all${C_RESET}                        # detiene todas las instancias
  ${C_CYAN}$0 stop development${C_RESET}                # detiene las 3 dev
  ${C_CYAN}$0 b panda-kitsune frontend${C_RESET}         # alias de build: rebuild del frontend de Panda
  ${C_CYAN}$0 build dev backend${C_RESET}                # rebuild solo del backend de dev
  ${C_CYAN}$0 restart panda-kitsune app${C_RESET}        # reinicia solo el servicio app
  ${C_CYAN}$0 logs development:mm-boutique frontend${C_RESET}  # tail logs del frontend dev
  ${C_CYAN}$0 exec development:morales-store app bash${C_RESET} # bash dentro del app dev
  ${C_CYAN}$0 status all${C_RESET}                       # estado de los servicios
  ${C_CYAN}$0 info${C_RESET}                             # tabla detallada de todos los containers
  ${C_CYAN}$0 info development:mm-boutique${C_RESET}      # tabla detallada de un stack dev
  ${C_CYAN}$0 nuke panda-kitsune${C_RESET}               # ⚠ BORRA la BD de Panda
  ${C_CYAN}$0 setup${C_RESET}                            # primera instalación (all)
  ${C_CYAN}$0 setup development${C_RESET}                # primera instalación solo dev

Nota sobre dev:
  Cada tienda dev usa puertos shifted DISTINTOS para que las 3
  puedan correr en paralelo sin conflicto:
    panda-and-kitsune-dev: 6432/7379/9000/5321/2121 (+1000)
    mm-boutique-dev:       7432/8379/10000/6321/3121 (+2000)
    morals-store-dev:     8432/9379/11000/7321/4121 (+3000)
  Definidos en docker/instances/development/<store>/environment/.env
  Para operar una sola: start development:<store>.

EOF
}

# --- Dispatcher ------------------------------------------------------------

if [[ $# -lt 1 ]]; then
    cmd_help
    exit 0
fi

cmd="$1"; shift || true

case "$cmd" in
    # Aliases cortos para uso frecuente.
    b)        cmd_build "$@" ;;
    rs)       cmd_restart "$@" ;;
    ls)       cmd_ps "$@" ;;
    psv|info) cmd_info "$@" ;;

    up)       cmd_up "$@" ;;
    start)    cmd_start "$@" ;;
    stop)     cmd_stop "$@" ;;
    restart)  cmd_restart "$@" ;;
    build)    cmd_build "$@" ;;
    down)     cmd_down "$@" ;;
    nuke)     cmd_nuke "$@" ;;
    status)   cmd_status "$@" ;;
    ps)       cmd_ps "$@" ;;
    logs)     cmd_logs "$@" ;;
    exec)     cmd_exec "$@" ;;
    setup)    cmd_setup "$@" ;;
    hosts)    cmd_hosts "$@" ;;
    caddy)    cmd_caddy "$@" ;;
    help|-h|--help) cmd_help ;;
    *)
        log_err "Comando desconocido: $cmd"
        echo
        cmd_help
        exit 1
        ;;
esac