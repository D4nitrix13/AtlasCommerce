#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/db/backup.sh
# ------------------------------------------------------------
# cmd_db_backup <stage> <store>
#
# Exporta la BD del tenant actual a un archivo .sql.gz en
# db-backups/. Naming: <stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz
#
# Flags:
#   --no-compress   Genera .sql plain (no gzip). Default: gzip.
#   --schema-only   Solo DDL (CREATE TABLE, etc.), sin data.
#                   Util para fixtures que solo necesitan estructura.
#   --output <path> Path custom de salida (relativo a db-backups/
#                   o absoluto). Default: db-backups/<auto-name>.
#
# Output: cada paso se loguea con [step N/3] <desc>. Errores hacen
# exit 1 con mensaje claro.
#
# Uso:
#   cmd_db_backup dev panda-estampados
#   cmd_db_backup production mm-boutique --schema-only
#   cmd_db_backup dev panda-estampados --output db-backups/custom.sql.gz
# ------------------------------------------------------------
set -euo pipefail

cmd_db_backup() {
    if [[ $# -lt 2 ]]; then
        log_err "db-backup requiere: <stage> <store> [flags]"
        return 1
    fi
    local stage="$1"
    local store="$2"
    shift 2

    # Parsear flags
    local compress=1
    local schema_only=0
    local output_path=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --no-compress)
                compress=0
                shift
                ;;
            --schema-only)
                schema_only=1
                shift
                ;;
            --output)
                output_path="${2:-}"
                if [[ -z "$output_path" ]]; then
                    log_err "--output requiere <path>"
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

    # Generar nombre default si no se especifico --output.
    # El path siempre arranca con .sql (sin gzip). El step 2/3
    # agrega .gz si compress=1.
    if [[ -z "$output_path" ]]; then
        local ts
        ts="$(/usr/bin/date +%Y-%m-%d-%H%M)"
        output_path="db-backups/${stage}-${store}-${ts}.sql"
    fi

    # Path absoluto (resuelve ~, relativo, etc.)
    if [[ "$output_path" != /* ]]; then
        output_path="${ROOT_DIR}/${output_path}"
    fi

    # Crear directorio si no existe
    local output_dir
    output_dir="$(/usr/bin/dirname "$output_path")"
    /usr/bin/mkdir -p "$output_dir"

    log_bold "Exportando BD de ${first_inst}"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET}"
    log_info "Output: ${C_BOLD}${output_path}${C_RESET}"
    [[ $schema_only -eq 1 ]] && log_info "Modo: schema-only (sin data)"

    # ──────────────────────────────────────────────────────────
    # Step 1/3: pg_dump
    # ──────────────────────────────────────────────────────────
    log_step "1/3" "Ejecutando pg_dump"
    local dump_args=(--no-owner --clean --if-exists)
    [[ $schema_only -eq 1 ]] && dump_args+=(--schema-only)

    # NOTA: NO usamos docker_compose() wrapper porque loguea el
    # comando via log_cmd_block y eso contamina stdout (que
    # redirigimos al archivo). Usamos docker exec directo.
    # Container name format: <store>-<stage_alias>-<svc>
    local store_slug="${store}"
    local stage_alias="${stage}"
    [[ "$stage_alias" == "development" ]] && stage_alias="dev"
    [[ "$stage_alias" == "production" ]] && stage_alias="prod"
    local pg_container="${store_slug}-${stage_alias}-postgres"
    # User/DB Postgres siguen el slug del store (con guión → guión bajo)
    # porque el docker-compose del postgres no acepta guiones.
    # ej: panda-estampados → panda_estampados
    local pg_user_db="${store_slug//-/_}"
    # Si DB_USERNAME/DB_DATABASE vienen del .env (vía env_file), usar.
    # Si no, derivar del slug.
    local pg_user_effective="${DB_USERNAME:-$pg_user_db}"
    local pg_db_effective="${DB_DATABASE:-$pg_user_db}"

    /usr/bin/docker exec -i "$pg_container" \
        /usr/bin/pg_dump "${dump_args[@]}" \
            -U "$pg_user_effective" \
            -d "$pg_db_effective" \
        > "$output_path" 2>/tmp/pg_dump_err.log

    if [[ ! -s "$output_path" ]]; then
        log_err "pg_dump genero archivo vacio. Errores:"
        /usr/bin/cat /tmp/pg_dump_err.log
        /usr/bin/rm -f "$output_path"
        return 1
    fi
    local size
    size="$(/usr/bin/du -h "$output_path" | /usr/bin/cut -f1)"
    log_ok "Dump generado: ${size}"

    # ──────────────────────────────────────────────────────────
    # Step 2/3: Comprimir con gzip (si --no-compress no)
    # ──────────────────────────────────────────────────────────
    if [[ $compress -eq 1 && "$output_path" != *.gz ]]; then
        log_step "2/3" "Comprimiendo con gzip"
        /usr/bin/gzip --force "$output_path"
        output_path="${output_path}.gz"
        size="$(/usr/bin/du -h "$output_path" | /usr/bin/cut -f1)"
        log_ok "Comprimido: ${size}"
    fi

    # ──────────────────────────────────────────────────────────
    # Step 3/3: Verificar integridad + mostrar metadata
    # ──────────────────────────────────────────────────────────
    log_step "3/3" "Verificando integridad"
    if [[ "$output_path" == *.gz ]]; then
        /usr/bin/gunzip -t "$output_path" 2>/dev/null || {
            log_err "gz corrupto: $output_path"
            return 1
        }
    fi
    local lines
    lines="$(/usr/bin/wc -l < "$output_path")"
    log_ok "Archivo valido: ${lines} lineas, ${size}"

    log_bold "Backup completo"
    echo
    echo "  Para compartir con el equipo:"
    echo "    ${output_path}"
    echo
    echo "  ⚠ NO commitear a git. Compartir via Drive/S3/Slack."
}

# Helper: log_step consistente con bootstrap.sh
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}