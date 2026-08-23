#!/usr/bin/env bash
# scripts/docker/_lib/docker_compose.sh
# Wrapper sobre `docker compose` que se usa en cada comando.
# Automáticamente exporta HOST_UID/HOST_GID para que los servicios
# con `user: "${HOST_UID:-1000}"` matcheen el UID del usuario del host
# (típico cuando hay bind-mounts).

# Rutas absolutas para PATH hijacking. Si alguien pusiera un binario
# falso en /usr/local/bin o en el cwd, el wrapper sigue apuntando al
# docker real. Aplica también a id, porque lo usamos para detectar
# el UID del host.
readonly DOCKER_BIN="/usr/bin/docker"
readonly ID_BIN="/usr/bin/id"

# docker_compose <instance> <args...>
# Compone el comando final, lo loguea con formato multi-línea via
# log_cmd_block (cd + env + exec, coloreado por sección), y lo
# ejecuta dentro del directorio de la instancia. Exporta HOST_UID/
# HOST_GID para que el `user:` del compose tenga el UID correcto.
docker_compose() {
    local name="$1"; shift
    local dir
    dir="$(instance_compose_dir "$name")"
    local file
    file="$(instance_compose_file "$name")"
    local env_file
    env_file="$(instance_env_file "$name")"

    local host_uid="${HOST_UID:-$(${ID_BIN} -u)}"
    local host_gid="${HOST_GID:-$(${ID_BIN} -g)}"
    local env_string="HOST_UID=${host_uid} HOST_GID=${host_gid}"

    # Armar el array de tokens para `docker compose`. Separamos flags
    # (-f, --env-file) de valores posicionales para que log_cmd_block
    # los coloree distinto y los wrappee con `\` cuando aparece un flag.
    local compose_args=("${DOCKER_BIN}" "compose" "-f" "$file")
    if [[ -n "$env_file" ]]; then
        compose_args+=("--env-file" "${dir}/environment/.env")
    fi
    compose_args+=("$@")

    log_cmd_block "$dir" "$env_string" "${compose_args[@]}"

    # shellcheck disable=SC2086
    (cd "$dir" && HOST_UID="${host_uid}" HOST_GID="${host_gid}" ${DOCKER_BIN} compose -f "$file" $env_file "$@")
}

# docker_ps_project <project>
# Variante de `docker ps` filtrada por label de proyecto compose.
# Usado por cmd_ps() para mostrar todos los containers de una o
# más instancias en una sola tabla.
docker_ps_project() {
    ${DOCKER_BIN} ps --filter "label=com.docker.compose.project" \
        --format "table {{.Label \"com.docker.compose.project\"}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"
}

# docker_volume_inspect <vol> <format>
# Wrapper de `docker volume inspect` con fallback a "n/a" si el
# volumen no existe (caso: declarada en compose pero nunca creada).
docker_volume_inspect() {
    local vol="$1"
    local fmt="$2"
    ${DOCKER_BIN} volume inspect "$vol" --format "$fmt" 2>/dev/null \
        || printf "n/a"
}