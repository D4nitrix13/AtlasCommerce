#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/db/restore.sh
# ------------------------------------------------------------
# cmd_db_restore <stage> <store> <archivo>
#
# Carga un dump .sql o .sql.gz en la BD del tenant actual.
# SIEMPRE hace un backup pre-restore (safety net) antes de pisar.
#
# Flags:
#   --force              No pedir confirmacion. Default: pedir.
#   --no-pre-backup      No hacer backup pre-restore. Default: hacer.
#                        Util si estas probando y queres velocidad.
#   --skip-migrate       No correr migraciones despues del restore.
#                        Default: corre migrate (porque el dump puede
#                        estar desactualizado respecto al schema actual).
#   --truncate           DROP todo el schema antes del restore (mas
#                        limpio que el --clean del dump).
#                        Default: respetar --clean del dump.
#
# Uso:
#   cmd_db_restore dev panda-estampados \
#       dev-panda-estampados-2026-08-26-1430.sql.gz
#   cmd_db_restore production mm-boutique mm-boutique.sql.gz --force
# ------------------------------------------------------------
set -euo pipefail

cmd_db_restore() {
    if [[ $# -lt 3 ]]; then
        log_err "db-restore requiere: <stage> <store> <archivo> [flags]"
        return 1
    fi
    local stage="$1"
    local store="$2"
    local backup_file="$3"
    shift 3

    # Parsear flags
    local force=0
    local pre_backup=1
    local skip_migrate=0
    local truncate=0
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --force)
                force=1
                shift
                ;;
            --no-pre-backup)
                pre_backup=0
                shift
                ;;
            --skip-migrate)
                skip_migrate=1
                shift
                ;;
            --truncate)
                truncate=1
                shift
                ;;
            *)
                log_err "Flag desconocida: $1"
                return 1
                ;;
        esac
    done

    # Resolver path del archivo. Si es relativo:
    #   1. Primero intentar el path relativo al cwd del usuario.
    #   2. Si no existe, buscar en db-backups/ (convencion del repo).
    #   3. Si no existe tampoco, error.
    if [[ "$backup_file" != /* ]]; then
        if [[ ! -f "$backup_file" && -f "${ROOT_DIR}/db-backups/${backup_file}" ]]; then
            backup_file="${ROOT_DIR}/db-backups/${backup_file}"
        elif [[ ! -f "$backup_file" ]]; then
            backup_file="${ROOT_DIR}/${backup_file}"
        fi
    fi

    if [[ ! -f "$backup_file" ]]; then
        log_err "Archivo no existe: $backup_file"
        log_err "Tambien probe en: ${ROOT_DIR}/db-backups/${backup_file}"
        return 1
    fi

    # Resolver primera instancia del scope
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        return 1
    fi

    # Container + user/db Postgres (mismo patron que backup.sh)
    local store_slug="${store}"
    local stage_alias="${stage}"
    [[ "$stage_alias" == "development" ]] && stage_alias="dev"
    [[ "$stage_alias" == "production" ]] && stage_alias="prod"
    local pg_container="${store_slug}-${stage_alias}-postgres"
    # User/DB siguen el slug del store (con guión → guión bajo)
    # porque el docker-compose del postgres no acepta guiones.
    local pg_user_db="${store_slug//-/_}"
    local pg_user_effective="${DB_USERNAME:-$pg_user_db}"
    local pg_db_effective="${DB_DATABASE:-$pg_user_db}"

    log_bold "Restore de BD en ${first_inst}"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET}"
    log_info "Archivo: ${C_BOLD}${backup_file}${C_RESET}"
    local size
    size="$(/usr/bin/du -h "$backup_file" | /usr/bin/cut -f1)"
    log_info "Tamaño: ${size}"

    # ──────────────────────────────────────────────────────────
    # Confirmacion interactiva (a menos que --force)
    # ──────────────────────────────────────────────────────────
    if [[ $force -eq 0 ]]; then
        echo
        log_warn "Esto PISA la BD actual. Si tenes datos importantes,"
        log_warn "hace backup primero (./scripts/docker-stack.sh db-backup)."
        echo
        local answer
        /usr/bin/read -r -p "Continuar? [y/N] " answer
        if [[ ! "$answer" =~ ^[Yy]$ ]]; then
            log_info "Cancelado por el usuario."
            return 0
        fi
    fi

    # ──────────────────────────────────────────────────────────
    # Step 1/3: Pre-backup (safety net)
    # ──────────────────────────────────────────────────────────
    if [[ $pre_backup -eq 1 ]]; then
        log_step "1/3" "Pre-backup del estado actual"
        local pre_ts
        pre_ts="$(/usr/bin/date +%Y-%m-%d-%H%M)"
        local pre_path="db-backups/pre-restore-${stage}-${store}-${pre_ts}.sql.gz"
        if [[ "$pre_path" != /* ]]; then
            pre_path="${ROOT_DIR}/${pre_path}"
        fi
        /usr/bin/mkdir -p "$(/usr/bin/dirname "$pre_path")"
        /usr/bin/docker exec -i "$pg_container" \
            /usr/bin/pg_dump --no-owner --clean --if-exists \
                -U "$pg_user_effective" -d "$pg_db_effective" \
            | /usr/bin/gzip > "$pre_path"
        log_info "Pre-backup en: ${pre_path}"
    fi

    # ──────────────────────────────────────────────────────────
    # Step 2/3: Cargar el dump via psql
    # ──────────────────────────────────────────────────────────
    log_step "2/3" "Cargando dump via psql"

    # Si --truncate, ejecutar DROP SCHEMA + CREATE SCHEMA antes
    if [[ $truncate -eq 1 ]]; then
        log_info "Truncate: DROP SCHEMA public + CREATE SCHEMA public"
        /usr/bin/docker exec -i "$pg_container" /usr/bin/psql \
            -U "$pg_user_effective" -d "$pg_db_effective" \
            -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;" 2>&1 | /usr/bin/grep -v "^$"
    fi

    # Ejecutar el dump via docker exec directo (NO docker_compose,
    # porque loguea a stdout y contaminaria el stream de psql)
    if [[ "$backup_file" == *.gz ]]; then
        /usr/bin/gunzip -c "$backup_file" | /usr/bin/docker exec -i "$pg_container" /usr/bin/psql \
            -U "$pg_user_effective" -d "$pg_db_effective" \
            --set ON_ERROR_STOP=on 2>&1 | /usr/bin/grep -E "(ERROR|CREATE|INSERT|ALTER|DROP)" | /usr/bin/head -20
    else
        /usr/bin/cat "$backup_file" | /usr/bin/docker exec -i "$pg_container" /usr/bin/psql \
            -U "$pg_user_effective" -d "$pg_db_effective" \
            --set ON_ERROR_STOP=on 2>&1 | /usr/bin/grep -E "(ERROR|CREATE|INSERT|ALTER|DROP)" | /usr/bin/head -20
    fi

    local psql_exit=$?
    if [[ $psql_exit -ne 0 ]]; then
        log_err "psql fallo con exit $psql_exit. BD puede estar en estado inconsistente."
        log_err "El pre-backup esta en db-backups/ para rollback."
        return 1
    fi
    log_ok "Dump cargado"

    # ──────────────────────────────────────────────────────────
    # Step 3/3: Correr migraciones (para actualizar schema)
    # ──────────────────────────────────────────────────────────
    if [[ $skip_migrate -eq 1 ]]; then
        log_step "3/3" "Migraciones SKIPPED (--skip-migrate)"
    else
        log_step "3/3" "Corriendo migraciones (por si el dump esta desactualizado)"
        local backend_container="${store_slug}-${stage_alias}-backend"
        /usr/bin/docker exec -i "$backend_container" \
            /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true
        log_ok "Migraciones aplicadas"
    fi

    log_bold "Restore completo de ${first_inst}"
    echo
    echo "  Verificaciones sugeridas:"
    echo "    ./scripts/docker-stack.sh exec $stage $store backend bash"
    echo "    curl -sI http://${store}.test:9000/api/storefront/theme"
}

log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}