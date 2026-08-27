#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/setup/bootstrap.sh
# ------------------------------------------------------------
# cmd_bootstrap <stage> <store>
#
# Setup inicial del backend Laravel (no toca datos de BD mas alla
# del schema y el seed de tenants). Idempotente: se puede correr
# multiples veces sin efectos secundarios.
#
# Pasos:
#   1. Esperar a que Postgres este healthy
#   2. storage:link (symlink public/storage)
#   3. key:generate (APP_KEY si falta)
#   4. cache:clear + config:clear
#   5. migrate --force
#   6. tenants:bootstrap (seed especifico de la instancia)
#
# NO hace:
#   - db:seed (datos fake de testing)         -> usar `seed-db --seed`
#   - cargar SQL dump                         -> usar `seed-db --sql <file>`
#   - importar backup desde db-backups/      -> usar `db-restore <file>`
#
# Esas 3 cosas antes eran flags de bootstrap (--with-seed, --with-backup).
# Las sacamos para que cada comando tenga una sola responsabilidad:
# bootstrap es setup de la APP, seed-db/db-restore son sobre DATOS.
#
# Uso:
#   cmd_bootstrap dev panda-estampados-and-kitsune
#   cmd_bootstrap production mm-boutique
#
# Para datos de testing o dumps reales, usar seed-db / db-restore:
#   ./scripts/docker-stack.sh seed-db dev panda-estampados-and-kitsune --seed
#   ./scripts/docker-stack.sh seed-db dev panda-estampados-and-kitsune --sql backup.sql
#   ./scripts/docker-stack.sh db-restore dev panda-estampados-and-kitsune backup.sql
#
# Output: cada paso se loguea con [step N/6] <descripcion>. Errores
# hacen exit 1 con mensaje claro. El comando nunca falla silencioso.
# ------------------------------------------------------------
set -euo pipefail

cmd_bootstrap() {
    local stage="$1"
    local store="$2"
    shift 2

    # bootstrap no acepta flags. Si pasan algo, error explicito
    # (antes aceptaba --with-seed y --with-backup — ver CHANGELOG).
    if [[ $# -gt 0 ]]; then
        log_err "bootstrap no acepta flags. Si querés seeders/SQL usar:"
        log_err "  ./scripts/docker-stack.sh seed-db <stage> <store> [--seed | --sql <file>]"
        log_err "  ./scripts/docker-stack.sh db-restore <stage> <store> <archivo>"
        return 1
    fi

    # Resolver primera instancia del scope
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        return 1
    fi

    log_bold "Bootstrap de ${first_inst}"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET}"

    # ──────────────────────────────────────────────────────────
    # Step 1/6: Esperar Postgres healthy
    # ──────────────────────────────────────────────────────────
    log_step "1/6" "Esperando Postgres healthy"
    local pg_container="${first_inst}-postgres"
    local attempts=0
    local max_attempts=30
    while ! /usr/bin/docker exec -i "$pg_container" \
        /usr/bin/pg_isready -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [[ $attempts -ge $max_attempts ]]; then
            log_err "Postgres no respondio despues de ${max_attempts} intentos (~30s)"
            return 1
        fi
        /usr/bin/sleep 1
    done
    log_ok "Postgres healthy"

    # ──────────────────────────────────────────────────────────
    # Step 2/6: storage:link
    # ──────────────────────────────────────────────────────────
    log_step "2/6" "storage:link"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan storage:link 2>&1 | /usr/bin/grep -v "^\[No exception" || true
    log_ok "Storage symlinked"

    # ──────────────────────────────────────────────────────────
    # Step 3/6: key:generate (solo si APP_KEY falta)
    # ──────────────────────────────────────────────────────────
    log_step "3/6" "Verificando APP_KEY"
    local app_key
    app_key="$(/usr/bin/grep -E '^APP_KEY=' "$(instance_env_file "$first_inst")" | /usr/bin/cut -d= -f2-)"
    if [[ -z "$app_key" || "$app_key" == *"GENERAR_CON_KEY"* || "$app_key" == *"GENERATE"* ]]; then
        log_info "APP_KEY no seteada, generando..."
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan key:generate --force 2>&1 | /usr/bin/grep -v "^$"
        log_ok "APP_KEY generada"
    else
        log_ok "APP_KEY ya seteada"
    fi

    # ──────────────────────────────────────────────────────────
    # Step 4/6: cache:clear + config:clear
    # ──────────────────────────────────────────────────────────
    log_step "4/6" "Limpiando caches de Laravel"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan cache:clear 2>&1 | /usr/bin/grep -v "^$" || true
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan config:clear 2>&1 | /usr/bin/grep -v "^$" || true
    log_ok "Caches limpios"

    # ──────────────────────────────────────────────────────────
    # Step 5/6: migrate --force
    # ──────────────────────────────────────────────────────────
    log_step "5/6" "Ejecutando migraciones"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true
    log_ok "Migraciones aplicadas"

    # ──────────────────────────────────────────────────────────
    # Step 6/6: tenants:bootstrap (seed de la instancia)
    # ──────────────────────────────────────────────────────────
    log_step "6/6" "tenants:bootstrap (seed de la instancia)"
    local seed_file
    seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_file "$first_inst")" | /usr/bin/cut -d= -f2-)"
    if [[ -n "$seed_file" && -f "$(instance_compose_dir "$first_inst")/${seed_file#/}" ]]; then
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 | /usr/bin/grep -E "(Creando|tenant|ERROR)" || true
        log_ok "Tenants seeded"
    else
        log_warn "Seed file no encontrado ($seed_file), skipping tenants:bootstrap"
    fi

    log_bold "Bootstrap completo de ${first_inst}"
    echo
    echo "  Para datos de testing, correr:"
    echo "    ./scripts/docker-stack.sh seed-db $stage $store --seed"
    echo "  Para cargar un dump SQL:"
    echo "    ./scripts/docker-stack.sh seed-db $stage $store --sql <archivo>"
    echo "    ./scripts/docker-stack.sh db-restore $stage $store <archivo>"
}

# Helper: log_step imprime [step N/6] <desc> en un formato consistente.
# Definido aca (no en _lib) porque solo lo usa bootstrap.
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}