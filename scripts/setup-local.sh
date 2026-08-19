#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/setup-local.sh — habilita las 4 tiendas en 127.0.0.1
# ------------------------------------------------------------
# Aplica Caddyfile + /etc/hosts y deja los comandos de
# verificación listos. Requiere sudo.
# ------------------------------------------------------------
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CADDY_SRC="${ROOT_DIR}/Caddyfile"
CADDY_DST="/etc/caddy/Caddyfile"
HOSTS_FILE="/etc/hosts"

if [[ ! -f "${CADDY_SRC}" ]]; then
    echo "❌ No encuentro ${CADDY_SRC}" >&2
    exit 1
fi

# Hosts a mapear a 127.0.0.1
HOSTS=(
    panda-estampados.com
    www.panda-estampados.com
    admin.panda-estampados.com
    kitsune.com
    www.kitsune.com
    admin.kitsune.com
    mm-boutique.com
    www.mm-boutique.com
    admin.mm-boutique.com
    morales-store.com
    www.morales-store.com
    admin.morales-store.com
)

# 1. Backup de /etc/hosts y de Caddyfile
sudo cp "${HOSTS_FILE}" "${HOSTS_FILE}.bak.$(date +%Y%m%d-%H%M%S)"
sudo cp "${CADDY_DST}" "${CADDY_DST}.bak.$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true

# 2. Copiar Caddyfile y formatear
sudo cp "${CADDY_SRC}" "${CADDY_DST}"
sudo caddy fmt --overwrite "${CADDY_DST}"

# 3. Inyectar hosts en /etc/hosts (idempotente)
add_host() {
    local host="$1"
    if grep -qE "[[:space:]]${host//./\\.}$" "${HOSTS_FILE}"; then
        echo "  ✓ ${host} ya estaba"
    else
        echo "127.0.0.1  ${host}" | sudo tee -a "${HOSTS_FILE}" >/dev/null
        echo "  + ${host} agregado"
    fi
}

echo "==> Hosts a 127.0.0.1"
for host in "${HOSTS[@]}"; do
    add_host "${host}"
done

# 4. Validar Caddy
echo "==> Validando Caddyfile"
sudo caddy validate --config "${CADDY_DST}"

# 5. Recargar Caddy
echo "==> Reiniciando Caddy"
if systemctl is-active --quiet caddy; then
    # `systemctl reload` falla cuando la config no expone la admin API
    # (admin off). El restart asegura la nueva config desde cero.
    sudo systemctl reset-failed caddy.service 2>/dev/null || true
    sudo systemctl restart caddy.service
else
    echo "  ⚠ caddy.service inactivo. Levantálo con: sudo systemctl start caddy"
fi

cat <<'NOTE'

✅ Setup completo. Verificaciones sugeridas:

  curl -sI http://panda-estampados.com/api/storefront/theme
  curl -sI http://kitsune.com/api/storefront/theme
  curl -sI http://admin.panda-estampados.com/login
  curl -sI http://mm-boutique.com/api/storefront/theme
  curl -sI http://morales-store.com/api/storefront/theme

Para correr las instancias Docker (puerto por instancia):
  cd docker/instances/panda-estampados && docker compose up -d
  cd docker/instances/mm-boutique  && docker compose up -d
  cd docker/instances/morales-store && docker compose up -d

Para revertir los cambios:
  sudo cp ${HOSTS_FILE}.bak.* ${HOSTS_FILE}
  sudo cp ${CADDY_DST}.bak.* ${CADDY_DST}
  sudo systemctl reload caddy
NOTE
