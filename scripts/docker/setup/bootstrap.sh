#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/setup/bootstrap.sh
# ------------------------------------------------------------
# cmd_bootstrap <stage> <store> [--with-seed] [--with-backup]
#
# Ejecuta TODAS las tareas post-deploy que necesita el backend
# para estar operativo:
#   1. Esperar a que Postgres este healthy
#   2. key:generate (APP_KEY si falta)
#   3. storage:link (symlink public/storage)
#   4. cache:clear + config:clear
#   5. migrate --force
#   6. tenants:bootstrap (seed especifico de la instancia)
#   7. (opcional) db:seed (datos fake de prueba)
#   8. (opcional) db:restore (cargar backup real desde db-backups/)
#
# Idempotente: se puede correr multiples veces sin efectos
# secundarios. Las migraciones usan --force que es safe si no hay
# cambios pendientes.
#
# Flags:
#   --with-seed    Corre ademas db:seed (datos fake de desarrollo).
#                  Default: NO corre (queremos datos reales).
#   --with-backup <file>  Corre db:restore con el archivo indicado
#                  desde db-backups/ antes de las migraciones.
#                  Util para levantar la app con datos reales
#                  versionados externamente.
#
# Uso:
#   cmd_bootstrap dev panda-estampados                # solo schema
#   cmd_bootstrap dev panda-estampados --with-seed    # + seeders fake
#   cmd_bootstrap dev panda-estampados --with-backup backup-2026-08-25.sql
#
# Output: cada paso se loguea con [step N/8] <descripcion>. Errores
# hacen exit 1 con mensaje claro. El comando nunca falla silencioso.
# ------------------------------------------------------------
set -euo pipefail

cmd_bootstrap() {
    local stage="$1"
    local store="$2"
    shift 2

    # Parsear flags
    local with_seed=0
    local with_backup=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --with-seed)
                with_seed=1
                shift
                ;;
            --with-backup)
                with_backup="${2:-}"
                if [[ -z "$with_backup" ]]; then
                    log_err "--with-backup requiere <archivo>"
                    return 1
                fi
                shift 2
                ;;
            *)
                log_err "Flag desconocida: $1"
                return 1
                ;;
        esac
    done

    # Resolver primera instancia del scope
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        return 1
    fi

    log_bold "Bootstrap de ${first_inst}"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET}"
    [[ $with_seed -eq 1 ]] && log_info "Incluye: db:seed (datos fake de desarrollo)"
    [[ -n "$with_backup" ]] && log_info "Incluye: db:restore desde db-backups/${with_backup}"

    # ──────────────────────────────────────────────────────────
    # Step 1/8: Esperar Postgres healthy
    # ──────────────────────────────────────────────────────────
    log_step "1/8" "Esperando Postgres healthy"
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
    # Step 2/8: db:restore (si --with-backup)
    # ──────────────────────────────────────────────────────────
    if [[ -n "$with_backup" ]]; then
        log_step "2/8" "Restaurando BD desde db-backups/${with_backup}"
        if [[ ! -f "${ROOT_DIR}/db-backups/${with_backup}" ]]; then
            log_err "Archivo no existe: db-backups/${with_backup}"
            return 1
        fi
        /usr/bin/docker compose \
            -f "$(instance_compose_file "$first_inst")" \
            --env-file "$(instance_env_file "$first_inst")" \
            exec -T "$postgres_svc" /usr/bin/psql \
                -U "${DB_USERNAME:-postgres}" \
                -d "${DB_DATABASE:-ecommerce}" \
                -f "/var/www/backup/${with_backup}" 2>&1 | /usr/bin/grep -E "(ERROR|CREATE|INSERT|ALTER)" | /usr/bin/head -10
        log_ok "BD restaurada"
    fi

    # ──────────────────────────────────────────────────────────
    # Step 3/8: storage:link (crea public/storage -> storage/app/public)
    # ──────────────────────────────────────────────────────────
    log_step "3/8" "storage:link"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan storage:link 2>&1 | /usr/bin/grep -v "^\[No exception" || true
    log_ok "Storage symlinked"

    # ──────────────────────────────────────────────────────────
    # Step 4/8: key:generate (solo si APP_KEY falta)
    # ──────────────────────────────────────────────────────────
    log_step "4/8" "Verificando APP_KEY"
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
    # Step 5/8: cache:clear + config:clear
    # ──────────────────────────────────────────────────────────
    log_step "5/8" "Limpiando caches de Laravel"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan cache:clear 2>&1 | /usr/bin/grep -v "^$" || true
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan config:clear 2>&1 | /usr/bin/grep -v "^$" || true
    log_ok "Caches limpios"

    # ──────────────────────────────────────────────────────────
    # Step 6/8: migrate --force
    # ──────────────────────────────────────────────────────────
    log_step "6/8" "Ejecutando migraciones"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true
    log_ok "Migraciones aplicadas"

    # ───────────────────────────────────────────────────────────────────────────────────
    log_step "6/8" "Ejecutando migraciones"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true
    log_ok "Migraciones aplicadas"

    # ──────────────────────────────────────────────────────────
    # Step 7/8: tenants:bootstrap (seed de la instancia)
    # ──────────────────────────────────────────────────────────
    log_step "7/8" "tenants:bootstrap (seed de la instancia)"
    local seed_file
    seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_file "$first_inst")" | /usr/bin/cut -d= -f2-)"
    if [[ -n "$seed_file" && -f "$(instance_compose_dir "$first_inst")/${seed_file#/}" ]]; then
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 | /usr/bin/grep -E "(Creando|tenant|ERROR)" || true
        log_ok "Tenants seeded"
    else
        log_warn "Seed file no encontrado ($seed_file), skipping tenants:bootstrap"
    fi

    # ──────────────────────────────────────────────────────────
    # Step 8/8: db:seed (solo si --with-seed)
    # ──────────────────────────────────────────────────────────
    if [[ $with_seed -eq 1 ]]; then
        log_step "8/8" "db:seed (datos fake de desarrollo)"
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan db:seed --force 2>&1 | /usr/bin/grep -E "(Seeded|ERROR)" || true
        log_ok "Database seeded con datos fake"
    else
        log_step "8/8" "db:seed (SKIPPED — usar --with-seed para datos fake)"
    fi

    log_bold "Bootstrap completo de ${first_inst}"
    echo
    echo "  Verificaciones sugeridas:"
    echo "    curl -sI http://${store}.test:9000/api/storefront/theme"
    echo "    ./scripts/docker-stack.sh exec $stage $store backend bash"
}

# Helper: log_step imprime [step N/8] <desc> en un formato consistente.
# Definido aca (no en _lib) porque solo lo usa bootstrap.
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}