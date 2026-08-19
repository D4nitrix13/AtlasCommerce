#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/help/registry.sh
# ------------------------------------------------------------
# Registro centralizado de `cmd_help_<comando>` para todos los
# comandos del orquestador docker-stack. El dispatcher lo sourcea
# cuando el usuario pide help por subcomando (ej:
# ./scripts/docker-stack.sh volumes help).
#
# Convención de la función:
#   - Sin argumentos (ignora el área porque el help es del
#     comando, no del scope).
#   - Salida por stdout (cat heredoc).
#   - Lineas ≤ 80 chars (regla del proyecto).
#   - Colores via C_* (de colors.sh) — se deshabilitan en
#     TTY no interactivo si fuera necesario.
# ------------------------------------------------------------

# ─────────────────────────────────────────────────────────────
# Lifecycle
# ─────────────────────────────────────────────────────────────

cmd_help_up() {
    cat <<'EOF'
[up] build + start containers (convenio docker compose).

USO:
  $0 up [<área>] [<servicio>]

ÁREA:
  all | prod | dev | production:<store> | development:<store>
  default: all

EJEMPLOS:
  $0 up                          # 6 instancias
  $0 up dev                      # 3 dev
  $0 up dev backend              # 1 servicio (3 dev backend)
  $0 up production:morales-store # 1 instancia

NOTA:
  Equivale a `docker compose up -d --build`. Si una imagen ya
  está al día, no la rebuilda.
EOF
}

cmd_help_start() {
    cat <<'EOF'
[start] levantar containers sin rebuild.

USO:
  $0 start [<área>] [<servicio>]

EJEMPLOS:
  $0 start                  # todas las 6
  $0 start dev              # las 3 dev
  $0 start dev mailpit      # solo el servicio mailpit de las 3 dev

DIFERENCIA CON up:
  start = `docker compose start` (no rebuild); up = build + start.
EOF
}

cmd_help_stop() {
    cat <<'EOF'
[stop] detener containers preservando volúmenes.

USO:
  $0 stop [<área>] [<servicio>]

EJEMPLOS:
  $0 stop                 # las 6
  $0 stop prod            # las 3 prod
  $0 stop dev backend     # solo backend de las 3 dev

NOTA:
  Los volúmenes nombrados NO se borran (usar rm-volumes para eso).
EOF
}

cmd_help_restart() {
    cat <<'EOF'
[restart] reiniciar containers.

USO:
  $0 restart [<área>] [<servicio>]

EJEMPLOS:
  $0 restart dev                # las 3 dev
  $0 restart dev backend        # backend de las 3 dev
  $0 restart panda-and-kitsune  # 1 instancia (default = prod)
EOF
}

cmd_help_build() {
    cat <<'EOF'
[build] rebuild imágenes (--no-cache).

USO:
  $0 build [<área>] [<servicio>]

EJEMPLOS:
  $0 build                          # las 6
  $0 build dev                      # las 3 dev
  $0 build dev backend              # backend de las 3 dev
  $0 build production:panda-and-kitsune frontend

NOTA:
  Alias corto: b. Si querés limpiar completamente el cache de
  Docker antes, usá: docker builder prune
EOF
}

cmd_help_down() {
    cat <<'EOF'
[down] detener y eliminar containers (preserva volúmenes).

USO:
  $0 down [<área>] [<servicio>]

EJEMPLOS:
  $0 down                  # las 6
  $0 down dev              # las 3 dev
  $0 down production       # las 3 prod

NOTA:
  Los volúmenes nombrados (postgres_data, storage, etc.) NO se
  borran. Para eso, rm-volumes.
EOF
}

cmd_help_nuke() {
    cat <<'EOF'
[nuke]  down + eliminar volúmenes (BORRA DATOS).

USO:
  $0 nuke [<área>] [<servicio>]

EJEMPLOS:
  $0 nuke dev           # ⚠ borra TODOS los volúmenes de las 3 dev

⚠ DESTRUCTIVO:
  Borra datos persistidos: bases Postgres, storage de Laravel,
  node_modules de pnpm, caches de Astro/Vite. Confirmar antes.
  Después de nuke hay que correr setup dev para recrear.
EOF
}

# ─────────────────────────────────────────────────────────────
# Inspección
# ─────────────────────────────────────────────────────────────

cmd_help_status() {
    cat <<'EOF'
[status] compose ps de las instancias seleccionadas.

USO:
  $0 status [<área>]

EJEMPLOS:
  $0 status                # las 6
  $0 status dev            # las 3 dev

SALIDA:
  Lista service, state, ports por container de cada instancia
  target. Para vista detallada (image, command, networks), usá
  info en su lugar.
EOF
}

cmd_help_ps() {
    cat <<'EOF'
[ps] lista rápida de containers (vista `docker ps` filtrada).

USO:
  $0 ps [<área>]

EJEMPLOS:
  $0 ps            # las 6
  $0 ps dev        # las 3 dev

ALIAS:
  ls

DIFERENCIA CON status:
  ps = vista rápida de docker ps. status = compose ps por
  instancia. Para detalles por container, info.
EOF
}

cmd_help_info() {
    cat <<'EOF'
[info] tabla detallada: instancia · servicio · estado · puertos.

USO:
  $0 info [<área>] [<servicio>]

EJEMPLOS:
  $0 info             # las 6
  $0 info dev         # las 3 dev
  $0 info dev nginx   # solo nginx de las 3 dev

ALIAS:
  psv
EOF
}

cmd_help_logs() {
    cat <<'EOF'
[logs] tail de logs de containers.

USO:
  $0 logs <instancia> [<servicio>]

EJEMPLOS:
  $0 logs development:panda-and-kitsune
  $0 logs dev backend                  # 3 dev, backend
  $0 logs production:morales-store app

ATAJOS:
  Ctrl-C para salir. Para que siga al attach, agregar `-f` está
  implícito; pasar `--since` o `--tail` como flag de docker compose.
EOF
}

# ─────────────────────────────────────────────────────────────
# Volumes
# ─────────────────────────────────────────────────────────────

cmd_help_volumes() {
    cat <<'EOF'
[volumes] listar volúmenes Docker declarados por las instancias.

USO:
  $0 volumes [<área>] [<servicio>]

EJEMPLOS:
  $0 volumes                       # todas (6)
  $0 volumes dev                   # 3 dev
  $0 volumes dev backend           # backend de las 3 dev
  $0 volumes production            # 3 prod

ALIAS:
  vol
EOF
}

cmd_help_rm_volumes() {
    cat <<'EOF'
[rm-volumes] eliminar volúmenes (⚠ BORRA DATOS).

USO:
  $0 rm-volumes [-y] [<área>] [<servicio>]

FLAGS:
  -y   saltar confirmación interactiva (usar con cuidado)

EJEMPLOS:
  $0 rm-volumes dev                # pedir confirmación
  $0 rm-volumes -y dev backend    # sin confirmación, solo backend

⚠ DESTRUCTIVO:
  Borra los volúmenes seleccionados sin posibilidad de recovery.
  Pensar dos veces antes de pasar -y.
EOF
}

# ─────────────────────────────────────────────────────────────
# Exec
# ─────────────────────────────────────────────────────────────

cmd_help_exec() {
    cat <<'EOF'
[exec] ejecutar comando dentro de un container.

USO:
  $0 exec <instancia> <servicio> <comando...>

EJEMPLOS:
  $0 exec development:mm-boutique app bash
  $0 exec dev backend php artisan migrate --seed
  $0 exec production:panda-and-kitsune app ls -la /var/www/html

NOTA:
  Si el servicio tiene varios containers (ej: nginx + php-fpm),
  toma el principal. Para apuntar a uno específico, usar el label
  compose manualmente (docker compose exec --index N ...).
EOF
}

# ─────────────────────────────────────────────────────────────
# Setup
# ─────────────────────────────────────────────────────────────

cmd_help_setup() {
    cat <<'EOF'
[setup] primera instalación: copiar .env.example y arrancar.

USO:
  $0 setup [<área>]

EJEMPLOS:
  $0 setup             # preparar todas las 6
  $0 setup dev         # preparar las 3 dev
  $0 setup prod        # preparar las 3 prod

QUÉ HACE:
  1. Copia docker/instances/<stage>/<store>/environment/.env.example
     a .env si este no existe.
  2. Construye imágenes (docker compose build).
  3. Levanta los containers (docker compose up -d).
  4. Corre migraciones y seeders base.
EOF
}

cmd_help_hosts() {
    cat <<'EOF'
[hosts] imprime el bloque para /etc/hosts.

USO:
  $0 hosts

SALIDA:
  Lista de líneas para pegar en /etc/hosts con las IPs internas
  de cada dominio. Útil para que Caddy del host resuelva.

EJEMPLO DE USO:
  sudo ./scripts/setup-local.sh        # automágico
  # o manual: sudo tee -a /etc/hosts <<< "$(./scripts/docker-stack.sh hosts)"
EOF
}

cmd_help_caddy() {
    cat <<'EOF'
[caddy] imprime el bloque Caddyfile para esta instalación.

USO:
  $0 caddy

SALIDA:
  Bloque de configuración Caddy (puerto 80/443) para los
  dominios de las instancias. Útil cuando se corre Caddy
  por fuera de docker.

SI USÁS DOCKER COMPOSE DE Caddy, IGNORAR.
EOF
}

# ─────────────────────────────────────────────────────────────
# Help
# ─────────────────────────────────────────────────────────────

cmd_help_help() {
    cat <<'EOF'
[help] mostrar ayuda.

USO:
  $0 help                         # global
  $0 <comando> help               # per-comando
  $0 <comando> <área> help        # per-comando con área

ATAJOS:
  -h, --help en cualquier posición se tratan como 'help'.

DESCUBRIR MÁS:
  $0 help            # este mensaje
  $0 <comando> help # detalles del comando (uso, ejemplos)
EOF
}
