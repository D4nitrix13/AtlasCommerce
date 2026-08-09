#!/usr/bin/env bash
# scripts/docker/_lib/resolve.sh
# Constantes y funciones para resolver instancias / stage / servicio.
# Cada comando del orquestador las usa para mapear el argumento
# "production:panda-and-kitsune" o "dev backend" al directorio y
# compose file correctos.

# ROOT_DIR apunta al repo raíz. Calculado en base a la ubicación del
# script actual (BASH_SOURCE) para que el script sea portable sin
# importar desde dónde se invoque.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && /usr/bin/pwd)"
INSTANCES_DIR="${ROOT_DIR}/docker/instances"

# Las instancias se identifican con un scope: `production:<store>` o
# `development:<store>`. Sin scope, se asume `production:` (default).
# Esto permite que el mismo nombre de tienda exista en prod y dev
# (panda-and-kitsune es multi-tenant en ambos mundos, pero vive en
# carpetas distintas y con compose / Dockerfiles distintos).
PROD_INSTANCES=(production:panda-and-kitsune production:mm-boutique production:morales-store)
DEV_INSTANCES=(development:panda-and-kitsune development:mm-boutique development:morales-store)
ALL_INSTANCES=("${PROD_INSTANCES[@]}" "${DEV_INSTANCES[@]}")

# is_valid_instance <name>
is_valid_instance() {
    local name="$1"
    for inst in "${ALL_INSTANCES[@]}"; do
        [[ "$inst" == "$name" ]] && return 0
    done
    return 1
}

# instance_compose_dir <name>
# Resuelve la ruta ABSOLUTA al directorio de la instancia según el scope.
# Scope explícito (production:<store> / development:<store>) tiene
# prioridad. Sin scope, default = production.
# Esta es la única función que sabe dónde vive cada instancia en el FS.
instance_compose_dir() {
    local name="$1"
    case "$name" in
        production:*) echo "${INSTANCES_DIR}/production/${name#production:}" ;;
        development:*) echo "${INSTANCES_DIR}/development/${name#development:}" ;;
        *) echo "${INSTANCES_DIR}/production/${name}" ;;
    esac
}

# instance_compose_file <name>
# Todas las instancias usan `docker-compose.yml` en su carpeta.
instance_compose_file() {
    echo "docker-compose.yml"
}

# instance_env_file <name>
# Devuelve el flag --env-file con la ruta absoluta al .env de la
# instancia, o string vacío si no existe (compose usará sus defaults).
# Usado por docker_compose() para pasar variables de entorno.
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
# Variante que también captura un servicio opcional. Imprime líneas
# "<instancia>\t<servicio>". Si servicio está vacío, opera sobre todo
# el stack. Permite que cada comando opere a nivel de stack o de un
# servicio individual.
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
#   resolve_targets development:mm-boutique → 1 dev específica
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
# Si la instancia no tiene environment/.env (solo .env.example), lo
# copia y avisa. Idempotente: no pisa si ya existe.
ensure_env_file() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ ! -f "${dir}/environment/.env" ]]; then
        if [[ -f "${dir}/environment/.env.example" ]]; then
            log_warn "${name}: environment/.env no existe — copiando .env.example (editá los secretos antes de producción)."
            /usr/bin/cp "${dir}/environment/.env.example" "${dir}/environment/.env"
            /usr/bin/chmod 644 "${dir}/environment/.env"
        else
            log_err "${name}: no hay environment/.env ni environment/.env.example en ${dir}"
            exit 1
        fi
    fi
}