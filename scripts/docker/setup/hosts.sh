#!/usr/bin/env bash
# scripts/docker/setup/hosts.sh
# cmd_hosts
# Imprime el bloque para agregar a /etc/hosts (resolucion local de
# los dominios de las instancias).
#
# Genera 3 subdominios por tienda (base + www + admin) a partir del
# array STORES definido en resolve.sh. Esto evita el bug previo donde
# instrumentos-musicales-la-voz (primer cliente real SaaS) no aparecia
# en el bloque (commit 25d59bff agrego La Voz como tienda pero NO
# actualizo este script).
cmd_hosts() {
    /usr/bin/cat <<EOF
# Pegar este bloque al final de /etc/hosts (con sudo).
# Convención local: dominios con TLD '.test' (RFC 2606) para que
# resuelvan solo en la maquina del dev. No se publican a internet.
# Acceso: http://<store>.test:5321 (frontend dev) o
#         http://<store>.test:4321 (frontend prod).
EOF
    # STORES vive en resolve.sh y se carga via all.sh antes que este
    # script (orden: colors -> logging -> resolve -> summary ->
    # docker_compose). Por eso esta disponible aca.
    local store
    for store in "${STORES[@]}"; do
        /usr/bin/printf '127.0.0.1  %s.test\n' "$store"
        /usr/bin/printf '127.0.0.1  www.%s.test\n' "$store"
        /usr/bin/printf '127.0.0.1  admin.%s.test\n' "$store"
    done
}