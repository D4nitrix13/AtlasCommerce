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
# Comandos:
#   start   [instancia|all]      Levantar containers (preserva volúmenes)
#   stop    [instancia|all]      Detener containers (preserva volúmenes)
#   restart [instancia|all]      Reiniciar containers
#   status  [instancia|all]      Estado de cada servicio
#   ps      [instancia|all]      Lista de containers
#   logs    [instancia] [svc]    Tail de logs (default: todos)
#   exec    <instancia> <svc> <cmd...>
#                                Ejecuta <cmd> dentro del container
#   down    [instancia|all]      Detener y eliminar containers (preserva volúmenes)
#   nuke    [instancia|all]      down + eliminar volúmenes (BORRA DATOS)
#   setup                        Primera vez: copia .env.example, levanta todo
#   hosts                        Imprime el bloque para /etc/hosts
#   caddy                        Imprime el Caddyfile a usar
#   help                         Muestra esta ayuda
#
# Instancias válidas:
#   panda-kitsune  (multi-tenant: Panda + Kitsune)
#   mm-boutique    (single-tenant)
#   morales-store  (single-tenant)
#   dev           (entorno local con bind mounts + xdebug)
#   all           = todas las de producción + dev
#
# Requiere: docker compose v2 (>= 5.x), bash 4+.
# ------------------------------------------------------------
set -euo pipefail

# --- Constantes ------------------------------------------------------------

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INSTANCES_DIR="${ROOT_DIR}/docker/instances"
DEV_DIR="${ROOT_DIR}/docker/dev"

PROD_INSTANCES=(panda-kitsune mm-boutique morales-store)
ALL_INSTANCES=(panda-kitsune mm-boutique morales-store dev)

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
instance_compose_dir() {
    local name="$1"
    if [[ "$name" == "dev" ]]; then
        echo "${DEV_DIR}"
    else
        echo "${INSTANCES_DIR}/${name}"
    fi
}

# instance_compose_file <name>
# Dev usa docker-compose.dev.yml; las instancias usan docker-compose.yml.
instance_compose_file() {
    local name="$1"
    if [[ "$name" == "dev" ]]; then
        echo "docker-compose.dev.yml"
    else
        echo "docker-compose.yml"
    fi
}

# instance_env_file <name>
# Dev usa .env; las instancias usan el .env del directorio de la instancia.
instance_env_file() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ -f "${dir}/.env" ]]; then
        echo "--env-file ${dir}/.env"
    else
        echo ""
    fi
}

# resolve_instances <arg>
# "all" → todas; un nombre → esa; vacío → error.
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
# Si la instancia no tiene .env (solo .env.example), lo copia y avisa.
ensure_env_file() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ "$name" == "dev" ]]; then
        return 0
    fi
    if [[ ! -f "${dir}/.env" ]]; then
        if [[ -f "${dir}/.env.example" ]]; then
            log_warn "${name}: .env no existe — copiando .env.example (editá los secretos antes de producción)."
            cp "${dir}/.env.example" "${dir}/.env"
            chmod 644 "${dir}/.env"
        else
            log_err "${name}: no hay .env ni .env.example en ${dir}"
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

cmd_start() {
    local arg="${1:-all}"
    while read -r inst; do
        ensure_env_file "$inst"
        log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" up -d --remove-orphans
        log_ok "${inst} levantado."
    done < <(resolve_instances "$arg")
}

cmd_stop() {
    local arg="${1:-all}"
    while read -r inst; do
        log_info "Deteniendo ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" stop
        log_ok "${inst} detenido (containers preservados)."
    done < <(resolve_instances "$arg")
}

cmd_restart() {
    local arg="${1:-all}"
    while read -r inst; do
        ensure_env_file "$inst"
        log_info "Reiniciando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" restart
        log_ok "${inst} reiniciado."
    done < <(resolve_instances "$arg")
}

cmd_down() {
    local arg="${1:-all}"
    while read -r inst; do
        log_info "Bajando containers de ${C_BOLD}${inst}${C_RESET} (preserva volúmenes)…"
        docker_compose "$inst" down --remove-orphans
        log_ok "${inst} abajo."
    done < <(resolve_instances "$arg")
}

cmd_nuke() {
    local arg="${1:-all}"
    log_warn "⚠  nuke ELIMINA VOLÚMENES. Esto borra la base de datos completa."
    read -rp "¿Confirmás? escribí 'yes' para continuar: " confirm
    [[ "$confirm" == "yes" ]] || { log_info "Cancelado."; exit 0; }
    while read -r inst; do
        log_info "Nuke de ${C_BOLD}${inst}${C_RESET} (containers + volúmenes)…"
        docker_compose "$inst" down --volumes --remove-orphans
        log_ok "${inst} nuked."
    done < <(resolve_instances "$arg")
}

cmd_status() {
    local arg="${1:-all}"
    while read -r inst; do
        printf "\n${C_BOLD}==[ %s ]==${C_RESET}\n" "$inst"
        docker_compose "$inst" ps
    done < <(resolve_instances "$arg")
}

cmd_ps() {
    docker ps --filter "label=com.docker.compose.project" \
        --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
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

cmd_setup() {
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
    # 2. .env por instancia de producción
    for inst in "${PROD_INSTANCES[@]}"; do
        ensure_env_file "$inst"
    done
    # 3. Levantar todas las de producción
    cmd_start all
    # 4. Dev (opcional, sólo si .env existe)
    if [[ -f "${DEV_DIR}/.env" ]]; then
        cmd_start dev
    else
        log_warn "dev: .env no existe, omitido. Copiá docker/dev/.env.example cuando lo necesites."
    fi
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
  ${C_CYAN}$0 start all${C_RESET}                  # levanta las 4 instancias
  ${C_CYAN}$0 start mm-boutique${C_RESET}          # levanta solo MM Boutique
  ${C_CYAN}$0 stop dev${C_RESET}                  # detiene el dev compose
  ${C_CYAN}$0 restart panda-kitsune app${C_RESET}  # reinicia solo el servicio app
  ${C_CYAN}$0 logs mm-boutique frontend${C_RESET}  # tail logs del frontend
  ${C_CYAN}$0 exec morales-store app bash${C_RESET} # bash dentro del app
  ${C_CYAN}$0 status all${C_RESET}                 # estado de los servicios
  ${C_CYAN}$0 nuke panda-kitsune${C_RESET}         # ⚠ BORRA la BD de Panda
  ${C_CYAN}$0 setup${C_RESET}                      # primera instalación

EOF
}

# --- Dispatcher ------------------------------------------------------------

if [[ $# -lt 1 ]]; then
    cmd_help
    exit 0
fi

cmd="$1"; shift || true

case "$cmd" in
    start)    cmd_start "$@" ;;
    stop)     cmd_stop "$@" ;;
    restart)  cmd_restart "$@" ;;
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