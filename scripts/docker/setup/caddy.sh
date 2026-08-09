#!/usr/bin/env bash
# scripts/docker/setup/caddy.sh
# cmd_caddy
# Imprime el Caddyfile raíz con la config para enrutar las 6
# instancias (3 prod + 3 dev).
cmd_caddy() {
    /usr/bin/cat "${ROOT_DIR}/Caddyfile"
}