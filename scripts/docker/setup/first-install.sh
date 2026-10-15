#!/usr/bin/env bash
# scripts/docker/setup/first-install.sh
# cmd_setup <stage> <store|all>
# Primera instalación COMPLETA: copia docker/versions.env si falta,
# asegura .env de cada instancia, levanta los stacks, y luego corre
# el bootstrap del backend (storage:link + caches + key:generate +
# migrate + tenants:bootstrap) en orden. Acepta store=all para aplicar
# a todas las tiendas del stage.
#
# Después de setup la instancia está lista para recibir tráfico.
# Para datos de testing, correr `seed-db --seed` aparte.
set -euo pipefail

cmd_setup() {
    local stage="$1"
    local store="${2:-all}"
    log_bold "Primera instalación de las instancias."

    # 1. docker/versions.env (centraliza versiones de imágenes)
    if [[ ! -f "${ROOT_DIR}/docker/versions.env" ]]; then
        if [[ -f "${ROOT_DIR}/docker/versions.env.example" ]]; then
            log_info "Creando docker/versions.env desde .example (editá versiones si querés pinear)."
            /usr/bin/cp "${ROOT_DIR}/docker/versions.env.example" "${ROOT_DIR}/docker/versions.env"
        else
            log_err "docker/versions.env.example no existe. Repo corrupto?"
            exit 1
        fi
    fi

    # 2. Resolver qué instancias levanta este setup según (stage, store).
    # El dispatcher ya normalizó stage (prod→production, dev→development).
    local -a instances_to_start=()
    while IFS=$'\t' read -r inst _; do
        instances_to_start+=("$inst")
    done < <(resolve_targets "$stage" "$store")
    log_info "Scope: stage=${stage} store=${store} (${#instances_to_start[@]} instancias: ${instances_to_start[*]})"

    # 3. Asegurar .env de cada instancia.
    for inst in "${instances_to_start[@]}"; do
        ensure_env_file "$inst"
    done

    # 3.5. Avisar si los hosts de las instancias a levantar NO estan
    # en /etc/hosts. Sin esta entrada, las URLs tipo
    # 'http://instrumentos-musicales-la-voz.test:10321' no resuelven
    # en el browser aunque el stack este arriba. No podemos escribir
    # a /etc/hosts sin sudo, asi que el usuario tiene que correr el
    # bloque manualmente (con sudo). Mostrar el bloque completo al
    # inicio de setup para que el usuario lo tenga a mano.
    local missing_hosts=()
    local inst store_slug store_hosts entry
    local host_entry
    for inst in "${instances_to_start[@]}"; do
        store_slug="${inst#*:}"
        store_hosts=(
            "${store_slug}.test"
            "www.${store_slug}.test"
            "admin.${store_slug}.test"
        )
        for host_entry in "${store_hosts[@]}"; do
            if ! /usr/bin/grep -qxE "[[:space:]]*[^#][[:space:]]+${host_entry}([[:space:]]|$)" /etc/hosts 2>/dev/null; then
                missing_hosts+=("$host_entry")
            fi
        done
    done
    if [[ ${#missing_hosts[@]} -gt 0 ]]; then
        log_warn "Faltan ${#missing_hosts[@]} entradas en /etc/hosts para acceder a las tiendas:"
        for entry in "${missing_hosts[@]}"; do
            log_info "  - ${entry}"
        done
        log_warn "Pegá este bloque al final de /etc/hosts (con sudo):"
        for entry in "${missing_hosts[@]}"; do
            /usr/bin/printf '127.0.0.1  %s\n' "$entry"
        done
        log_info "Sugerencia: ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts"
    fi

    # 4. Levantar containers.
    for inst in "${instances_to_start[@]}"; do
        log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" up -d --remove-orphans
        log_ok "${inst} levantado."
    done

    # 5. Bootstrap del backend Laravel en cada instancia levantada.
    # Secuencia: storage:link → cache:clear → config:clear → migrate →
    # tenants:bootstrap. `key:generate` lo hace `cmd_bootstrap` al final
    # (idempotente, regenera APP_KEY si falta).
    #
    # NOTA sobre extracción del slug: inst viene como `stage:store-slug`,
    # por ejemplo `development:panda-estampados-and-kitsune`. Cortamos
    # SOLO el prefijo `stage:` con ${var#*:} para preservar guiones
    # internos del slug (que tiene varios `-`). NO usar ${var%%-*} porque
    # cortaría al primer guion y rompería el lookup en resolve_targets.
    for inst in "${instances_to_start[@]}"; do
        log_bold "Backend bootstrap de ${inst}"
        local stage_of_inst="${inst%%:*}"
        local store_of_inst="${inst#*:}"

        # Esperar Postgres healthy antes de cualquier comando artisan.
        # Dev: container local <slug>-dev-postgres. Prod: DB externa, sin
        # container local -> skipea el check (asume DB ya operativa).
        local pg_container=""
        if [[ "$stage_of_inst" == "development" ]]; then
            pg_container="${store_of_inst}-dev-postgres"
        fi
        if [[ -n "$pg_container" ]] && \
            /usr/bin/docker ps --format '{{.Names}}' 2>/dev/null \
                | /usr/bin/grep -qx "$pg_container"; then
            local attempts=0
            local max_attempts=30
            while ! /usr/bin/docker exec -i "$pg_container" \
                /usr/bin/pg_isready -U "${DB_USERNAME:-${store_of_inst//-/_}}" >/dev/null 2>&1; do
            attempts=$((attempts + 1))
            if [[ $attempts -ge $max_attempts ]]; then
                log_err "Postgres de ${inst} no respondio despues de ${max_attempts} intentos"
                return 1
            fi
            /usr/bin/sleep 1
        done
        fi

        log_info "storage:link"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan storage:link 2>&1 | /usr/bin/grep -v "^\[No exception" || true

        # CRITICAL: el volumen storage/ se crea como root en el build,
        # pero PHP-FPM corre como UID 1000 (host user). Sin el chown,
        # view:clear falla y el primer 404/500 rompe porque PHP no puede
        # escribir los views compilados en storage/framework/views/.
        log_info "chown storage 1000:1000 (PHP-FPM user)"
        docker_compose "$inst" exec -u root -T backend \
            /bin/chown -R 1000:1000 /var/www/html/storage 2>&1 || true

        log_info "cache:clear + config:clear + view:clear"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan cache:clear 2>&1 | /usr/bin/grep -v "^$" || true
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan config:clear 2>&1 | /usr/bin/grep -v "^$" || true
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan view:clear 2>&1 | /usr/bin/grep -v "^$" || true

        log_info "assets:sync-from-public (logos + favicons al FTP)"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan assets:sync-from-public --force 2>&1 | /usr/bin/grep -E "(Subido|Resumen)" || true

        log_info "migrate --force"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true

        log_info "tenants:bootstrap (seed de la instancia)"
        local seed_file
        seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_path "$inst")" | /usr/bin/cut -d= -f2-)"
        if [[ -n "$seed_file" && -f "$(instance_compose_dir "$inst")/${seed_file#/}" ]]; then
            docker_compose "$inst" exec -T backend \
                /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 | /usr/bin/grep -E "(Creando|tenant|ERROR)" || true
        else
            log_warn "Seed file no encontrado ($seed_file), skipping tenants:bootstrap"
        fi

        # key:generate (idempotente, via cmd_bootstrap).
        cmd_bootstrap "$stage_of_inst" "$store_of_inst"
    done

    log_ok "Setup completo. Próximos pasos:"
    echo "  1. sudo ./scripts/docker-stack.sh hosts >>/etc/hosts"
    echo "  2. sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy"
    echo "  3. Probar: curl -sI http://panda-estampados.com/api/storefront/theme"
}
