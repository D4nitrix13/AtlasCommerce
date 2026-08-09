#!/usr/bin/env sh
# entrypoint-frontend.dev.sh — Entrypoint para el contenedor frontend en dev.
# Garantiza que los volúmenes montados (node_modules, .pnpm-store, .astro)
# sean escribibles por el usuario `node` (UID 1000). Sin esto, los
# volúmenes creados por docker compose son propiedad de root y el dev
# server falla con EACCES al intentar escribir en /app/node_modules o
# /app/.pnpm-store.
#
# Convención: la imagen NO declara USER; este entrypoint corre como
# root. Después del chown, hace `su -c` para ejecutar pnpm como node
# (mínimo privilegio). `setpriv` de busybox no soporta --reuid/--regid
# en esta versión, por eso usamos `su`.

set -eu

if [ "$(id -u)" = "0" ]; then
    for dir in node_modules .pnpm-store .astro; do
        if [ -d "/app/$dir" ]; then
            /bin/chown -R node:node "/app/$dir"
        fi
    done

    # Drop privilegios y exec pnpm como node (UID 1000).
    exec /bin/su -s /bin/sh node -c '/usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"' --
fi

# Si por alguna razón ya entramos como node, saltar el chown y correr pnpm directo.
exec /usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"