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
#
# Limpieza de procesos stale: `astro dev` mantiene un archivo `.astro/dev.json`
# con el PID del proceso anterior. Cuando el container se reinicia, ese
# PID queda huérfano y astro lo detecta como "ya corriendo", crasheando
# el dev server. Limpiamos `.astro/` antes de invocar pnpm para forzar
# un estado limpio.

set -eu

if [ "$(id -u)" = "0" ]; then
    for dir in node_modules .pnpm-store; do
        if [ -d "/app/$dir" ]; then
            /bin/chown -R node:node "/app/$dir"
        fi
    done

    # Limpiar contenidos de caches stale (no los mount points — `rm -rf`
    # sobre un mount falla con EBUSY). Borrar el .astro/dev.json force-
    # enables cold-start del dev server cuando un container anterior dejó
    # el lock file con un PID huérfano (incident 2026-08-17).
/usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true
/usr/bin/find /app/node_modules/.vite -mindepth 1 -delete 2>/dev/null || true
    # Matar cualquier astro/vite stale que haya sobrevivido (defense-in-depth).
/usr/bin/pkill -f 'astro dev' 2>/dev/null || true
/usr/bin/pkill -f 'vite'      2>/dev/null || true

    # Drop privilegios y exec pnpm como node (UID 1000).
    exec /bin/su -s /bin/sh node -c '/usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"' --
fi

# Si por alguna razón ya entramos como node, saltar el chown y correr pnpm directo.
# Misma limpieza de caches antes de iniciar.
/usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true
/usr/bin/find /app/node_modules/.vite -mindepth 1 -delete 2>/dev/null || true
exec /usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"
