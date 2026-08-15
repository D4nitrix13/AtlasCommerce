#!/usr/bin/env bash
# scripts/docker/mailpit-up.sh — Helper para levantar mailpit en una
# instancia dev específica. Mailpit está declarado como servicio en
# cada docker-compose.yml; este script solo orquesta el up.
#
# Uso:
#   ./scripts/docker/mailpit-up.sh development:panda-and-kitsune
#   ./scripts/docker/mailpit-up.sh dev:mm-boutique
#   ./scripts/docker/mailpit-up.sh dev:morales-store
#   # Para todos:
#   ./scripts/docker/mailpit-up.sh all
#
# UI de mailpit por instancia:
#   panda-and-kitsune → http://localhost:8025
#   mm-boutique       → http://localhost:18025
#   morales-store     → http://localhost:28025

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Una sola instancia específica.
start_one() {
    local instance="$1"
    local compose_file="${ROOT_DIR}/docker/instances/development/${instance}/docker-compose.yml"
    if [[ ! -f "${compose_file}" ]]; then
        echo "[mailpit-up] ERROR: compose file no encontrado: ${compose_file}"
        return 1
    fi
    echo "[mailpit-up] Levantando mailpit para ${instance}..."
    docker compose -f "${compose_file}" up -d mailpit
}

# Todas las instancias dev.
start_all() {
    for inst in panda-and-kitsune mm-boutique morales-store; do
        start_one "$inst" || true
    done
}

target="${1:-all}"
case "$target" in
    all)
        start_all
        ;;
    development:* | dev:*)
        start_one "${target##*:}"
        ;;
    *)
        start_one "$target"
        ;;
esac
