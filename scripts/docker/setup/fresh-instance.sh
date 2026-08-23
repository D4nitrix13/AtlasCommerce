#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/setup/fresh-instance.sh
# ------------------------------------------------------------
# cmd_fresh <instancia> [--build]
#
# Fresh start de UNA instancia: borra todo, rebuild, initdb si
# aplica, migrations, seeders, tenants:bootstrap. Idempotente en
# errores (cada paso se loguea con [ok]/[warn]/[error]).
#
# Cierra los gaps que el setup command no cubre:
#   1. El compose dev/postgres override `command:` bypassa el
#      docker-entrypoint.sh del image, que es el que corre initdb.
#      En un volume fresh, postgres no puede arrancar. Por eso el
#      pre-initdb se hace manualmente via docker compose run.
#   2. initdb solo crea el cluster con la base `postgres`; el nombre
#      real (panda_estampados) hay que crearlo con `createdb`.
#
# Uso:
#   $0 cmd_fresh development:panda-estampados
#   $0 cmd_fresh production:panda-estampados
#
# Requiere:
#   - ./scripts/docker-stack.sh (mismo wrapper que el orquestador)
#   - docker, docker compose v2
#   - Bash 4+
# ------------------------------------------------------------
cmd_fresh() {
    local instance="${1:-}"
    local build_flag="${2:-}"
    if [[ -z "$instance" ]]; then
        log_err "Falta <instancia>."
        log_err "Ejemplos:"
        log_err "  $0 cmd_fresh development:panda-estampados"
        log_err "  $0 cmd_fresh production:panda-estampados"
        return 1
    fi

    log_bold "Fresh start de ${instance}"
    echo

    # 1. Verificar que la instancia existe.
    if ! is_valid_instance "$instance"; then
        log_err "Instancia desconocida: $instance"
        log_err "Válidas: ${ALL_INSTANCES[*]}"
        return 1
    fi

    # 2. Bajar y borrar volumes de la instancia (incluyendo named
    # volumes — fresh = clean slate).
    log_info "Bajando containers + borrando volumes de ${instance}…"
    if ! /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            down -v 2>&1 | /usr/bin/grep -E '^(Container|Volume|Network)'; then
        log_warn "docker compose down falló (¿no estaba corriendo?). Continúo."
    fi

    # 3. Levantar la instancia (build + start).
    log_info "Levantando ${instance} con build…"
    if [[ "$build_flag" == "--build" ]]; then
        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            up -d --build --remove-orphans 2>&1 | /usr/bin/tail -20
    else
        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            up -d --remove-orphans 2>&1 | /usr/bin/tail -20
    fi

    # 4. Pre-initdb workaround (dev / prod que usen el compose con
    # `command:` override). Si la imagen es postgis/postgis, el
    # entrypoint oficial NO corre por culpa del override; hay que
    # initdb manualmente.
    local stage="${instance%%:*}"
    local store="${instance##*:}"
    local pg_service="postgres"
    local pg_image
    pg_image=$(/usr/bin/docker compose \
        -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
        --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
        config --images 2>/dev/null | /usr/bin/awk -v svc="${pg_service}" '$0 ~ svc":" { print $2 }')
    log_info "Imagen postgres detectada: ${pg_image:-desconocida}"

    if /usr/bin/printf '%s' "$pg_image" | /usr/bin/grep -q 'postgis/postgis'; then
        log_info "Pre-initdb workaround (compose override bypasea entrypoint)…"

        # Detectar user/db de environment/.env
        local pg_user pg_db pg_pass
        pg_user=$(/usr/bin/grep '^POSTGRES_USER=' \
            "${SCRIPT_DIR}/docker/instances/${instance//:/}/environment/.env" \
            2>/dev/null | /usr/bin/head -1 | /usr/bin/cut -d= -f2)
        pg_db=$(/usr/bin/grep '^POSTGRES_DB=' \
            "${SCRIPT_DIR}/docker/instances/${instance//:/}/environment/.env" \
            2>/dev/null | /usr/bin/head -1 | /usr/bin/cut -d= -f2)
        pg_user="${pg_user:-postgres}"
        pg_db="${pg_db:-postgres}"

        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            run --rm \
            --entrypoint /usr/lib/postgresql/18/bin/initdb \
            -u 999:999 \
            "${pg_service}" \
            -D /var/lib/postgresql/18/docker \
            --auth-host=trust --auth-local=trust \
            -U "${pg_user}" -E UTF8 2>&1 | /usr/bin/tail -5

        # Reiniciar postgres para que arranque con el cluster inicializado.
        log_info "Reiniciando postgres para arrancar con cluster inicializado…"
        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            restart "${pg_service}" 2>&1 | /usr/bin/tail -3

        # Esperar a postgres healthy.
        log_info "Esperando a postgres healthy…"
        local container_name="${store}-${stage}-${pg_service}"
        local tries=30
        while (( tries-- > 0 )); do
            local health
            health=$(/usr/bin/docker inspect \
                --format='{{.State.Health.Status}}' "$container_name" 2>/dev/null)
            if [[ "$health" == "healthy" ]]; then
                break
            fi
            /usr/bin/sleep 2
        done
        if [[ "$health" != "healthy" ]]; then
            log_err "Postgres no quedó healthy en $((30*2))s. Abortando."
            return 1
        fi

        # Crear la DB si no existe.
        log_info "Creando DB ${pg_db} (si no existe)…"
        /usr/bin/docker exec "${container_name}" \
            /usr/lib/postgresql/18/bin/createdb -U "${pg_user}" "${pg_db}" 2>&1 \
            | /usr/bin/grep -E 'already exists|created' || true
    else
        log_info "Imagen postgres NO es postgis/postgis; saltando pre-initdb (asume entrypoint oficial)."
    fi

    # 5. Migrations + seeders.
    log_info "Migrations…"
    /usr/bin/docker compose \
        -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
        --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
        exec backend /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/tail -5

    log_info "Base seeders…"
    /usr/bin/docker compose \
        -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
        --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
        exec backend /usr/local/bin/php artisan db:seed --force 2>&1 | /usr/bin/tail -5

    # 6. tenants:bootstrap con el seed file del instance.
    local seed_file="${SCRIPT_DIR}/docker/instances/${instance//:/}/seeds/${instance##*:}.json"
    if [[ ! -f "$seed_file" ]]; then
        # Fallback: buscar dev-*.json en dev o *.json en prod
        seed_file=$(/usr/bin/find "${SCRIPT_DIR}/docker/instances/${instance//:/}/seeds/" \
            -name "*.json" 2>/dev/null | /usr/bin/head -1)
    fi
    if [[ -f "$seed_file" ]]; then
        log_info "tenants:bootstrap con ${seed_file##*/}…"
        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            exec -T backend /bin/sh -c "cat > /tmp/seed.json" \
            < "$seed_file"
        /usr/bin/docker compose \
            -f "${SCRIPT_DIR}/docker/instances/${instance//:/}/docker-compose.yml" \
            --project-directory "${SCRIPT_DIR}/docker/instances/${instance//:/}/.." \
            exec backend /usr/local/bin/php artisan tenants:bootstrap /tmp/seed.json 2>&1 \
            | /usr/bin/tail -5
    else
        log_warn "No hay seed JSON en ${instance}/seeds/; saltando tenants:bootstrap."
    fi

    echo
    log_ok "Fresh start completo: ${instance}"
    echo
    echo "Próximos pasos:"
    echo "  - Si vas a acceder desde el browser: agregá /etc/hosts:"
    echo "      127.0.0.1 ${store}.com"
    echo "    y visitá http://${store}.com:5321 (dev) o :4321 (prod)."
    echo "  - Mailpit UI (dev only): http://localhost:8025"
    echo "  - Adminer: usá la configuración del .env.example"
}
