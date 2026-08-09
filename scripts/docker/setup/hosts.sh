#!/usr/bin/env bash
# scripts/docker/setup/hosts.sh
# cmd_hosts
# Imprime el bloque para agregar a /etc/hosts (resolución local de
# los dominios de las 6 instancias).
cmd_hosts() {
    /usr/bin/cat <<'EOF'
# Pegar este bloque al final de /etc/hosts (con sudo):
127.0.0.1  panda-estampados.com
127.0.0.1  www.panda-estampados.com
127.0.0.1  admin.panda-estampados.com
127.0.0.1  kitsune.com
127.0.0.1  www.kitsune.com
127.0.0.1  admin.kitsune.com
127.0.0.1  mm-boutique.com
127.0.0.1  www.mm-boutique.com
127.0.0.1  admin.mm-boutique.com
127.0.0.1  morales-store.com
127.0.0.1  www.morales-store.com
127.0.0.1  admin.morales-store.com
EOF
}