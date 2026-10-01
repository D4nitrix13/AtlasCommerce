#!/usr/bin/env bash
# scripts/docker/setup/caddy.sh
# cmd_caddy
# Imprime el Caddyfile del repo con la config para enrutar las 6
# instancias (3 prod + 3 dev). Path: docker/caddy/Caddyfile.
cmd_caddy() {
    /usr/bin/cat "${ROOT_DIR}/docker/caddy/Caddyfile"
}