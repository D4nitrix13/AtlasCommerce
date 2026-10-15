#!/usr/bin/env sh
# entrypoint-frontend.dev.sh — Entrypoint para el contenedor frontend en dev.
# Garantiza que los volumenes montados (node_modules, node_modules/.bun,
# .astro) sean escribibles por el usuario `node` (UID 1000). Sin esto,
# los volumenes creados por docker compose son propiedad de root y el
# dev server falla con EACCES al intentar escribir en /app/node_modules
# o /app/node_modules/.bun.
#
# Convención: la imagen NO declara USER; este entrypoint corre como
# root. Despues del chown, hace `su -c` para ejecutar bun como node
# (mínimo privilegio). `setpriv` de busybox no soporta --reuid/--regid
# en esta versión, por eso usamos `su`.
#
# Limpieza de procesos stale: `astro dev` mantiene un archivo `.astro/dev.json`
# con el PID del proceso anterior. Cuando el container se reinicia, ese
# PID queda huérfano y astro lo detecta como "ya corriendo", crasheando
# el dev server. Limpiamos `.astro/` antes de invocar bun para forzar
# un estado limpio.

set -eu

# 'oven/bun:1-alpine' trae el user 'bun' con UID/GID 1000 pre-creado.
# No necesitamos crear un user 'node' (que ademas colisiona con
# bun por el UID 1000). El dev server corre como 'bun' para que
# pueda escribir al bind-mount de node_modules.

if [ "$(id -u)" = "0" ]; then
    for dir in node_modules node_modules/.bun .astro; do
        if [ -d "/app/$dir" ]; then
            /bin/chown -R bun:bun "/app/$dir"
        fi
    done

    # El named volume de node_modules shadow el bind-mount del host. Si
    # el host agrego un theme package nuevo (ej instrumentos-musicales-la-voz)
    # el named volume del container no lo refleja. Re-ejecutar bun install
    # resuelve los symlinks sin reconstruir la imagen.
    if [ -f /app/bun.lock ]; then
        cd /app && /usr/local/bin/bun install --frozen-lockfile 2>&1 | tail -3 || true
        /bin/chown -R bun:bun /app/node_modules /app/node_modules/.bun
    fi

    # Limpiar contenidos de caches stale (no los mount points — `rm -rf`
    # sobre un mount falla con EBUSY). Borrar el .astro/dev.json force-
    # enables cold-start del dev server cuando un container anterior dejó
    # el lock file con un PID huérfano (incident 2026-08-17).
    /usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true
    /usr/bin/find /app/node_modules/.vite -mindepth 1 -delete 2>/dev/null || true

    # Matar cualquier astro/vite stale que haya sobrevivido (defense-in-depth).
    /usr/bin/pkill -f 'astro dev' 2>/dev/null || true
    /usr/bin/pkill -f 'vite'      2>/dev/null || true

    # Drop privilegios y exec bun como el user 'bun' (UID 1000).
    # Usamos 'su -s /bin/sh - bun' (con nombre) porque 'bun' SI
    # existe en /etc/passwd de oven/bun:1-alpine.
    exec /bin/su -s /bin/sh bun -c '/usr/local/bin/bun run dev --host 0.0.0.0 --force "$@"' --
fi

# Si por alguna razon ya entramos como non-root, correr bun directo.
/usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true
/usr/bin/find /app/node_modules/.vite -mindepth 1 -delete 2>/dev/null || true
exec /usr/local/bin/bun run dev --host 0.0.0.0 --force "$@"

