#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/db/seed.sh
# ------------------------------------------------------------
# cmd_seed_db <stage> <store> [--seed] [--sql <archivo>]
#
# Migra y popula la BD del tenant actual. Comando FOCUSED:
# NO toca key:generate, storage:link, caches ni nada fuera de BD.
# Para setup completo (incluyendo pre-flight), usar `bootstrap`.
#
# Comportamiento por defecto:
#   - Corre `php artisan migrate --force` (siempre).
#   - NO corre seeders ni carga SQL — los datos existentes se preservan.
#
# Flags:
#   --seed              Corre `db:seed` despues de migrate (datos fake
#                       de testing). Default: NO.
#   --sql <archivo>     Carga un dump .sql/.sql.gz en la BD despues
#                       de migrate (datos reales / fixtures). El path
#                       se busca primero relativo al cwd; si no existe,
#                       se busca en db-backups/. Default: NO.
#
# Diferencias con otros comandos:
#   - bootstrap           Setup inicial completo + migrate + tenants:bootstrap
#                        (idempotente). Este seed-db es solo BD.
#   - bootstrap --with-seed     bootstrap + db:seed
#   - bootstrap --with-backup  bootstrap + db:restore desde archivo
#   - db-restore          Carga SQL + migrate. No corre seeders.
#   - seed-db             Migrate + (seeders OR sql). Foco en BD.
#   - fresh               down -v + rebuild + reinit + migrate + seed
#                        (DESTRUCTIVO: borra volumes).
#
# Uso:
#   cmd_seed_db dev panda-estampados-and-kitsune                  # solo migrate
#   cmd_seed_db dev panda-estampados-and-kitsune-and-kitsune --seed           # migrate + db:seed
#   cmd_seed_db dev panda-estampados-and-kitsune-and-kitsune --sql dump.sql   # migrate + restore
#   cmd_seed_db dev panda-estampados-and-kitsune-and-kitsune --seed --sql dump.sql
#                                                     # migrate + seed + restore
#   cmd_seed_db production mm-boutique                 # solo migrate prod
# ------------------------------------------------------------
set -euo pipefail

cmd_seed_db() {
    if [[ $# -lt 2 ]]; then
        log_err "seed-db requiere: <stage> <store> [--seed] [--sql <archivo>]"
        return 1
    fi
    local stage="$1"
    local store="$2"
    shift 2

    # Parsear flags. seed y sql son mutuamente excluyentes? NO.
    # Si pasan ambos, corre seeders Y carga SQL (despues del seed).
    local do_seed=0
    local sql_file=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --seed)
                do_seed=1
                shift
                ;;
            --sql)
                sql_file="${2:-}"
                if [[ -z "$sql_file" ]]; then
                    log_err "--sql requiere <archivo>"
                    return 1
                fi
                shift 2
                ;;
            *)
                log_err "Flag desconocida: $1"
                log_err "Uso: seed-db <stage> <store> [--seed] [--sql <archivo>]"
                return 1
                ;;
        esac
    done

    # Resolver primera instancia del scope.
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        return 1
    fi

    log_bold "Seed DB de ${first_inst}"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET}"
    [[ $do_seed -eq 1 ]] && log_info "Incluye: db:seed"
    [[ -n "$sql_file" ]] && log_info "Incluye: cargar SQL desde ${sql_file}"

    # ──────────────────────────────────────────────────────────
    # Step 1/3: Esperar Postgres healthy
    # ──────────────────────────────────────────────────────────
    log_step "1/3" "Esperando Postgres healthy"
    # Dev: container local <slug>-dev-postgres. Prod: DB externa, skip.
    local pg_container=""
    if [[ "$stage" == "development" ]]; then
        pg_container="${store}-dev-postgres"
    fi
    if [[ -n "$pg_container" ]] && \
        /usr/bin/docker ps --format '{{.Names}}' 2>/dev/null \
            | /usr/bin/grep -qx "$pg_container"; then
        local attempts=0
        local max_attempts=30
while ! /usr/bin/docker exec -i "$pg_container" \
        /usr/bin/pg_isready -U "${DB_USERNAME:-${store//-/_}}" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [[ $attempts -ge $max_attempts ]]; then
            log_err "Postgres no respondio despues de ${max_attempts} intentos (~30s)"
            return 1
        fi
        /usr/bin/sleep 1
    done
    fi
    log_ok "Postgres healthy"

    # ──────────────────────────────────────────────────────────
    # Step 2/3: php artisan migrate --force
    # ──────────────────────────────────────────────────────────
    log_step "2/3" "php artisan migrate --force"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan migrate --force
    log_ok "Migraciones aplicadas"

    # ──────────────────────────────────────────────────────────
    # Step 3/3: poblar (seeders y/o SQL, segun flags)
    # ──────────────────────────────────────────────────────────
    if [[ $do_seed -eq 1 ]]; then
        log_step "3a/3" "php artisan db:seed"
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan db:seed --force
        log_ok "Seeders aplicados"
    fi

    if [[ -n "$sql_file" ]]; then
        log_step "3b/3" "Cargando SQL: ${sql_file}"
        # Resolver path del archivo. Si es relativo:
        #   1. Primero intentar el path relativo al cwd.
        #   2. Si no existe, buscar en db-backups/.
        local resolved_sql=""
        if [[ -f "$sql_file" ]]; then
            resolved_sql="$sql_file"
        elif [[ -f "db-backups/${sql_file}" ]]; then
            resolved_sql="db-backups/${sql_file}"
        else
            log_err "Archivo SQL no encontrado: ${sql_file}"
            log_err "  Buscado en: ${sql_file} y db-backups/${sql_file}"
            return 1
        fi

        # Comprimir con gzip si el archivo no termina en .gz.
        local pg_user_db="${store//-/_}"
        local pg_user_effective="${DB_USERNAME:-$pg_user_db}"
        local pg_db_effective="${DB_DATABASE:-$pg_user_db}"

        if [[ "$resolved_sql" == *.gz ]]; then
            /usr/bin/cat "$resolved_sql" | /usr/bin/gunzip \
                | /usr/bin/docker exec -i "$pg_container" \
                    /usr/bin/psql -U "$pg_user_effective" -d "$pg_db_effective" \
                        --set ON_ERROR_STOP=on > /tmp/seed_db_load.log 2>&1 \
                || { log_err "psql fallo. Log:"; /usr/bin/cat /tmp/seed_db_load.log; return 1; }
        else
            /usr/bin/cat "$resolved_sql" \
                | /usr/bin/docker exec -i "$pg_container" \
                    /usr/bin/psql -U "$pg_user_effective" -d "$pg_db_effective" \
                        --set ON_ERROR_STOP=on > /tmp/seed_db_load.log 2>&1 \
                || { log_err "psql fallo. Log:"; /usr/bin/cat /tmp/seed_db_load.log; return 1; }
        fi
        log_ok "SQL cargado"
    fi

    if [[ $do_seed -eq 0 && -z "$sql_file" ]]; then
        log_info "(sin --seed ni --sql: solo se corrieron migraciones)"
    fi

    log_bold "Seed DB completo"
}

# Helper: log_step consistente con bootstrap.sh / db-backup.sh / sync-assets.
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}