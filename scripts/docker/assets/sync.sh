#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/assets/sync.sh
# ------------------------------------------------------------
# cmd_sync_assets <stage> <store> [--dry-run] [--force]
#
# Wrapper del orchestrator para `php artisan assets:sync-from-public`.
# Sincroniza los assets de marca commiteados en `brand-assets/`
# al FTP del tenant, bajo la convención documentada en
# docs/architecture/assets-naming-convention.md.
#
# Source (en este repo, versionado en git):
#     brand-assets/store/{slug}/logo.webp        # categoria A
#     brand-assets/store/{slug}/favicon.webp     # categoria A
#     brand-assets/shared/icons/social/*.webp    # categoria C
#
# Destino (en FTP, relativo a FTP_ROOT):
#     tenants/{slug}/branding/logo.webp
#     tenants/{slug}/branding/favicon.webp
#     shared/icons/social/facebook.webp
#
# Flags:
#   --dry-run    Solo reporta lo que haria, sin escribir en el FTP.
#   --force      Sobrescribe archivos existentes en el FTP.
#
# Comportamiento con store=all:
#   Cada backend container ve el mismo `brand-assets/` via bind mount
#   (ver docker-compose.yml), asi que llamar al artisan command SIN
#   --store sincroniza TODAS las tiendas. El wrapper usa la primera
#   instancia del scope solo como "entry point" — el resultado en el
#   FTP es independiente de qué backend se use.
#
#   Si querés sincronizar UNA sola tienda, pasala como store
#   especifico (no 'all'). El wrapper traduce eso a --store=<slug>
#   para el artisan command.
#
# Uso:
#   sync-assets dev panda-estampados-and-kitsune           # 1 tienda
#   sync-assets dev all                         # 3 tiendas
#   sync-assets dev all --dry-run               # preview sin escribir
#   sync-assets dev panda-estampados-and-kitsune-and-kitsune --force    # sobrescribir
#   sync-assets production mm-boutique
# ------------------------------------------------------------
set -euo pipefail

cmd_sync_assets() {
    if [[ $# -lt 2 ]]; then
        log_err "sync-assets requiere: <stage> <store> [--dry-run] [--force]"
        return 1
    fi
    local stage="$1"
    local store="$2"

    # Flags booleanos vienen como env vars seteadas por el dispatcher
    # (scripts/docker-stack.sh). Patrón consistente con HAS_NO_CACHE
    # para `build` y RM_VOLUMES_SKIP_CONFIRM para `rm-volumes`.
    local is_dry_run="${SYNC_ASSETS_DRY_RUN:-0}"
    local is_force="${SYNC_ASSETS_FORCE:-0}"

    # Resolver la primera instancia del scope. Para sync-assets alcanza
    # con UNA instancia porque todos los backends del stage comparten
    # el mismo brand-assets/ via bind mount (ver docker-compose.yml).
    local first_inst
    first_inst="$(resolve_targets "$stage" "$store" | /usr/bin/head -n 1 | /usr/bin/cut -f1)"
    if [[ -z "$first_inst" ]]; then
        log_err "Sin instancias para stage=$stage store=$store"
        return 1
    fi

    log_bold "Sync assets de marca → FTP"
    log_info "Stage: ${C_BOLD}${stage}${C_RESET} · Store: ${C_BOLD}${store}${C_RESET} · Container: ${C_BOLD}${first_inst}-backend${C_RESET}"
    [[ $is_dry_run -eq 1 ]] && log_warn "DRY-RUN: no se escribira en el FTP"
    [[ $is_force -eq 1 ]] && log_warn "FORCE: sobrescribira archivos existentes"

    # ──────────────────────────────────────────────────────────
    # Construir args para el artisan command.
    #
    # --store: se pasa solo cuando el wrapper recibe una tienda
    # especifica. Si es 'all', NO pasamos --store: el artisan command
    # sincroniza todas las tiendas que encuentre en brand-assets/store/.
    # ──────────────────────────────────────────────────────────
    local artisan_args=()
    if [[ "$store" != "all" ]]; then
        artisan_args+=("--store=${store}")
    fi
    [[ $is_dry_run -eq 1 ]] && artisan_args+=("--dry-run")
    [[ $is_force -eq 1 ]] && artisan_args+=("--force")

    log_step "1/1" "php artisan assets:sync-from-public ${artisan_args[*]}"
    docker_compose "$first_inst" exec -T backend \
        /usr/local/bin/php artisan assets:sync-from-public "${artisan_args[@]}"
}

# Helper: log_step consistente con bootstrap.sh / db-backup.sh.
log_step() {
    local n="$1"
    local desc="$2"
    log_info "[step ${n}] ${desc}"
}