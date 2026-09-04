#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/setup/bootstrap.sh
# ------------------------------------------------------------
# cmd_bootstrap <stage> <store>
#
# Regenera la APP_KEY del backend Laravel si está vacía o marcada
# con un placeholder. Idempotente: se puede correr múltiples veces
# sin efectos secundarios.
#
# Pasos:
#   1. Esperar a que Postgres este healthy
#   2. key:generate (APP_KEY si falta o es placeholder)
#
# NO hace:
#   - storage:link                 → lo hace `setup` en primera instalación
#   - cache:clear / config:clear   → lo hace `setup` en primera instalación
#   - migrate                      → lo hace `setup` (primera vez) o `seed-db` (resto)
#   - tenants:bootstrap            → lo hace `setup` en primera instalación
#   - db:seed                      → usar `seed-db --seed`
#   - cargar SQL dump              → usar `seed-db --sql <file>`
#   - importar backup              → usar `db-restore <archivo>`
#
# bootstrap es UN SOLO COMANDO con UNA SOLA RESPONSABILIDAD: regenerar
# APP_KEY. Es seguro correrlo en cualquier momento, incluso varias veces.
# Para el setup completo de primera instalación, usar `setup`.
#
# Uso:
#   cmd_bootstrap dev panda-estampados-and-kitsune
#   cmd_bootstrap production mm-boutique
#
# Output: cada paso se loguea con [step N/2] <descripcion>. Errores
# hacen exit 1 con mensaje claro. El comando nunca falla silencioso.
# ------------------------------------------------------------
set -euo pipefail

cmd_bootstrap() {
    local stage="$1"
    local store="$2"
    shift 2

    # bootstrap no acepta flags.
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
    # Step 1/2: Esperar Postgres healthy
    # ──────────────────────────────────────────────────────────
    log_step "1/2" "Esperando Postgres healthy"
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
    # Step 2/2: key:generate (solo si APP_KEY falta o es placeholder)
    # ──────────────────────────────────────────────────────────
    log_step "2/2" "Verificando APP_KEY"
    local app_key
    app_key="$(/usr/bin/grep -E '^APP_KEY=' "$(instance_env_file "$first_inst")" | /usr/bin/cut -d= -f2-)"
    if [[ -z "$app_key" || "$app_key" == *"GENERAR_CON_KEY"* || "$app_key" == *"GENERATE"* ]]; then
        log_info "APP_KEY no seteada o es placeholder, generando..."
        docker_compose "$first_inst" exec -T backend \
            /usr/local/bin/php artisan key:generate --force 2>&1 | /usr/bin/grep -v "^$"
        log_ok "APP_KEY generada"
    else
        log_ok "APP_KEY ya seteada"
    fi

    log_bold "Bootstrap completo de ${first_inst}"
}

# Helper: log_step imprime [step N/2] <desc> en un formato consistente.
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}
