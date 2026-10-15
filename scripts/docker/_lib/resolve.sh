#!/usr/bin/env bash
# scripts/docker/_lib/resolve.sh
# Constantes y funciones para resolver instancias / stage / store / servicio.
# Cada comando del orquestador las usa para mapear los argumentos
# `<stage> <store> [servicio]` al directorio y compose file correctos.
#
# Somos el ÚNICO módulo que sabe dónde vive cada instancia en el FS.
# El resto del orquestador consume instancias en formato `<stage>:<store>`
# (ej: `production:panda-estampados-and-kitsune`), el mismo que devuelve
# resolve_targets. Esto nos permite agregar / quitar tiendas tocando
# solo este archivo.

# ROOT_DIR apunta al repo raíz. Calculado en base a la ubicación del
# script actual (BASH_SOURCE) para que el script sea portable sin
# importar desde dónde se invoque.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && /usr/bin/pwd)"
INSTANCES_DIR="${ROOT_DIR}/docker/instances"

# Stages y stores disponibles como fuente de verdad única. Cualquier
# comando que liste o valide stages/stores consume estos arrays.
#
# `panda-estampados-and-kitsune` es una sola instancia Docker multi-tenant
# que hospeda 2 sub-tenants (Pandas Estampados + Kitsune). Renombrado
# desde `panda-estampados-and-kitsune` para que el nombre refleje la multi-tenancy
# y no parezca solo el tenant Pandas.
STAGES=(production development)
STORES=(panda-estampados-and-kitsune mm-boutique morales-store instrumentos-musicales-la-voz)

# Construimos ALL_INSTANCES (y las sublistas por stage) cruzando
# STAGES x STORES. Formato: `<stage>:<store>` — mismo formato que se
# consume en instance_compose_dir y friends. NO escribir estos arrays
# a mano: derivarlos de STAGES/STORES garantiza que agregar una tienda
# se propaga automáticamente.
ALL_INSTANCES=()
PROD_INSTANCES=()
DEV_INSTANCES=()
for _stage in "${STAGES[@]}"; do
    for _store in "${STORES[@]}"; do
        ALL_INSTANCES+=("${_stage}:${_store}")
        case "$_stage" in
            production) PROD_INSTANCES+=("${_stage}:${_store}") ;;
            development) DEV_INSTANCES+=("${_stage}:${_store}") ;;
        esac
    done
done
unset _stage _store

# is_valid_store <store>
# True si <store> es uno de los declarados en STORES.
is_valid_store() {
    local store="$1"
    for s in "${STORES[@]}"; do
        [[ "$s" == "$store" ]] && return 0
    done
    return 1
}

# is_valid_instance <name>
# True si <name> es un `<stage>:<store>` conocido (formato interno).
is_valid_instance() {
    local name="$1"
    for inst in "${ALL_INSTANCES[@]}"; do
        [[ "$inst" == "$name" ]] && return 0
    done
    return 1
}

# instance_compose_dir <name>
# Resuelve la ruta ABSOLUTA al directorio de la instancia según el
# stage. <name> debe venir en formato `<stage>:<store>`.
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

# instance_env_path <name>
# Variante de instance_env_file() que devuelve SOLO la ruta absoluta
# al .env (sin el flag --env-file). Usada por scripts que necesitan
# grep/awk sobre el archivo (instance_env_file no sirve porque el flag
# seria interpretado como opción).
instance_env_path() {
    local name="$1"
    local dir
    dir="$(instance_compose_dir "$name")"
    if [[ -f "${dir}/environment/.env" ]]; then
        echo "${dir}/environment/.env"
    fi
}

# resolve_targets <stage> <store> [svc]
# Variante canónica: stage y store como args separados. Imprime
# líneas `<instancia>\t<servicio>`, donde `<instancia>` está en
# formato `<stage>:<store>`. Si `store` es `all`, expande a todas
# las tiendas del stage.
#
# Args:
#   stage  → production | development | prod | dev (normalizado)
#   store  → uno de STORES, o 'all'
#   svc    → (opcional) nombre de servicio a pasar a compose
#
# Ejemplos:
#   resolve_targets development panda-estampados-and-kitsune → 1 instancia dev
#   resolve_targets dev all                             → 3 dev
#   resolve_targets production mm-boutique backend      → 1 prod con svc
#   resolve_targets prod all                            → 3 prod
resolve_targets() {
    local stage_raw="${1:-}"
    local store_raw="${2:-}"
    local svc="${3:-}"

    if [[ -z "$stage_raw" || -z "$store_raw" ]]; then
        log_err "Faltan argumentos. Uso: resolve_targets <stage> <store> [svc]"
        exit 1
    fi

    # Normalizar stage (acepta alias corto).
    local stage
    case "$stage_raw" in
        production|prod)     stage="production" ;;
        development|dev)     stage="development" ;;
        *) log_err "Stage inválido: $stage_raw. Válidos: production|prod, development|dev"
           exit 1 ;;
    esac

    # Determinar lista de instancias para este (stage, store).
    local -a instances=()
    if [[ "$store_raw" == "all" ]]; then
        for inst in "${ALL_INSTANCES[@]}"; do
            [[ "$inst" == "${stage}:"* ]] && instances+=("$inst")
        done
    elif is_valid_store "$store_raw"; then
        instances=("${stage}:${store_raw}")
    else
        log_err "Store inválido: $store_raw. Válidos: ${STORES[*]} o 'all'"
        exit 1
    fi

    local inst
    for inst in "${instances[@]}"; do
        printf "%s\t%s\n" "$inst" "$svc"
    done
}

# resolve_instances <arg>
# Deprecated: mantener por retrocompatibilidad interna (scripts que
# llaman resolve_instances en vez de resolve_targets). Devuelve solo
# el nombre de instancia, una por línea.
#
# Acepta la sintaxis legacy (`dev`, `production:<store>`, etc.) porque
# hay scripts externos (mailpit-up.sh) y referencias históricas que
# la usan. NO usar desde código nuevo del orquestador.
resolve_instances() {
    local arg="${1:-}"
    if [[ -z "$arg" ]]; then
        log_err "Falta la instancia. Usá 'all' o una de: ${ALL_INSTANCES[*]}"
        exit 1
    fi
    if [[ "$arg" == "all" ]]; then
        printf '%s\n' "${ALL_INSTANCES[@]}"
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
