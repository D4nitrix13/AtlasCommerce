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
# el dev server. Limpiamos el CONTENIDO de `.astro/` antes de invocar
# pnpm para forzar un estado limpio.
#
# IMPORTANTE: NO usar `rm -rf /app/.astro` porque `.astro/` es un VOLUME
# mount point — `rm -rf` sobre el mount point falla con "Resource busy" y
# rompe el container en restart loop. En su lugar, borramos el CONTENIDO
# con `find -mindepth 1 -delete`.

set -eu

if [ "$(id -u)" = "0" ]; then
    # IMPORTANTE: chown .astro ANTES del find -delete, porque Docker
    # crea volumes como root. Sin chown, astro dev corre como node y no
    # puede escribir a /app/.astro/types.d.ts (EACCES).
    for dir in node_modules .pnpm-store .astro; do
        if [ -d "/app/$dir" ]; then
            /bin/chown -R node:node "/app/$dir"
        fi
    done

    # Borrar CONTENIDO de /app/.astro (no el mount point) para forzar
    # un cold-start del dev server. El directorio mismo es regenerado
    # por astro dev al primer run.
    /usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true

    # Drop privilegios y exec pnpm como node (UID 1000).
    exec /bin/su -s /bin/sh node -c '/usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"' --
fi

# Si por alguna razón ya entramos como node, saltar el chown y correr pnpm directo.
# Misma limpieza de .astro/ antes de iniciar.
/usr/bin/find /app/.astro -mindepth 1 -delete 2>/dev/null || true
exec /usr/local/bin/pnpm run dev --host 0.0.0.0 --force "$@"
