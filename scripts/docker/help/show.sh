#!/usr/bin/env bash
# scripts/docker/help/show.sh
# cmd_help [comando]
#
# Pantalla de ayuda del orquestador Docker.
#
# Formas de uso:
#   ./scripts/docker-stack.sh help            -> índice compacto de todos los comandos
#   ./scripts/docker-stack.sh help <comando>  -> ayuda detallada de un comando específico
#   ./scripts/docker-stack.sh <cmd> --help    -> atajo del anterior
#
# Implementación: tablas Markdown-style (|---|) generadas manualmente
# con `printf`. Anchos calculados con `_ansi_strip` para que los colores
# ANSI en los nombres de comando NO rompan la alineación.
#
# Notas de estilo:
#   - Sin emojis, sin "(color X en ejemplos)", sin ruido.
#   - Comandos coloreados: magenta para normales, rojo brillante para
#     destructivos. Color configurable vía _BOX_CMD_COLOR antes de cada
#     tabla.

cmd_help() {
    local subcmd="${1:-}"

    if [[ -n "$subcmd" ]]; then
        _cmd_specific_help "$subcmd"
        return 0
    fi

    # ─── Help raíz: indice compacto (Track 1 T1.2 simplificacion) ─────
    #
    # Cambios desde la version anterior:
    #   - Aliases, puertos dev, sub-tenants y tabla de destructivos separados
    #     ELIMINADOS del help raiz (estan en docs/operations/ports.md y
    #     `help <cmd>` cuando hace falta). El help raiz muestra SOLO
    #     lo necesario para decidir que comando correr.
    #   - Destructivos integrados en la misma tabla con marker [D][X].
    #   - Salida ~50% mas corta (carga en pantalla mas rapido).
    printf "${C_BOLD}scripts/docker-stack.sh${C_RESET} — orquestador de las 6 instancias Docker\n\n"

    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${C_BRIGHT_MAGENTA}<comando>${C_RESET} [flags] ${C_BRIGHT_YELLOW}<stage>${C_RESET} ${C_BRIGHT_CYAN}<store|all>${C_RESET} [<servicio>]\n" "$0"
    printf "  %s ${C_BRIGHT_MAGENTA}<comando>${C_RESET} help   help detallado del comando\n\n" "$0"

    printf "${C_BOLD}Argumentos${C_RESET}\n"
    printf "  ${C_BRIGHT_YELLOW}<stage>${C_RESET}       production | development\n"
    printf "  ${C_BRIGHT_CYAN}<store>${C_RESET}       panda-estampados-and-kitsune | mm-boutique | morales-store | all\n"
    printf "  ${C_GREEN}[<servicio>]${C_RESET}  backend | frontend | postgres | redis | caddy | ...  (opcional)\n\n"

    printf "${C_BOLD}Comandos${C_RESET}   [D][X]=destructivo (BORRA datos, pide confirm)\n"
    # Una sola tabla — destructivos al final en rojo con marker [D][X].
    _box_draw 3 \
        "Comando"      "Flags"                      "Descripcion" \
        "start"        ""                           "Levantar containers existentes (sin rebuild)" \
        "stop"         ""                           "Detener containers (preserva estado)" \
        "restart"      ""                           "Reiniciar containers corriendo" \
        "up"           ""                           "Build + start (recrea si cambiaron Dockerfile/.env)" \
        "down"         ""                           "Detener + eliminar containers (preserva volumenes)" \
        "build"        "--no-cache"                 "Reconstruir imagenes Docker" \
        "ps"           ""                           "Vista global de containers de las 6 instancias" \
        "status"       ""                           "Estado por servicio de 1 instancia" \
        "info"         ""                           "Tabla: instancia + servicio + puertos + comando" \
        "logs"         ""                           "Tail -f de logs (servicio REQUERIDO)" \
        "volumes"      ""                           "Volumenes Docker agrupados con tamaño real" \
        "exec"         ""                           "Ejecutar comando dentro de un container" \
        "setup"        ""                           "Primera instalacion completa (.env + up + migrate)" \
        "bootstrap"    ""                           "Regenerar APP_KEY si falta (idempotente)" \
        "db-backup"    "--schema-only, --output"    "Exportar BD a .sql.gz" \
        "db-restore"   "--force, --truncate"        "Cargar dump en BD (DESTRUCTIVO)" \
        "seed-db"      "--seed, --sql <f>"          "Migrate + (--seed o --sql <archivo>)" \
        "sync-assets"  "--dry-run, --force"         "brand-assets/ → FTP via artisan" \
        "hosts"        ""                           "Bloque para /etc/hosts (sin args)" \
        "caddy"        ""                           "Caddyfile para enrutar 6 instancias (sin args)" \
        "help"         "[comando]"                  "Muestra este indice o detalle de un comando"

    # Destructivos en rojo (la tabla no necesita _BOX_CMD_COLOR por
    # default porque ya aplica magenta a la primera columna).
    _BOX_CMD_COLOR="${C_BRIGHT_RED}" _box_draw 3 \
        "Comando"      "Flags"   "Descripcion" \
        "nuke [D][X]"  ""        "down + eliminar volumenes (BORRA BD)" \
        "rm-volumes [D][X]" "-y"  "Eliminar volumenes sin tocar containers" \
        "fresh [D][X]" ""        "down -v + rebuild + migrate + seed + tenants"
    unset _BOX_CMD_COLOR

    printf "Detalle de un comando: ${C_BRIGHT_CYAN}%s ${C_BRIGHT_MAGENTA}<comando>${C_RESET} ${C_DIM}help${C_RESET}\n" "$0"
    printf "Aliases: ${C_BRIGHT_MAGENTA}b${C_RESET}=build ${C_BRIGHT_MAGENTA}rs${C_RESET}=restart ${C_BRIGHT_MAGENTA}ls${C_RESET}=ps ${C_BRIGHT_MAGENTA}psv${C_RESET}=info ${C_BRIGHT_MAGENTA}vol${C_RESET}=volumes ${C_BRIGHT_MAGENTA}rmvol${C_RESET}=rm-volumes\n\n"

    printf "${C_BOLD}Tiendas (store)${C_RESET}\n"
    printf "  ${C_BRIGHT_CYAN}panda-estampados-and-kitsune${C_RESET}  hospeda ${C_BOLD}2${C_RESET} sub-tenants (pandas-estampados.com B2B + kitsune.com B2C)\n"
    printf "  ${C_BRIGHT_CYAN}mm-boutique${C_RESET}                 B2C (moda, zapatos, bolsos)\n"
    printf "  ${C_BRIGHT_CYAN}morales-store${C_RESET}               retail general, abarrotes, hogar\n"
    printf "  ${C_BRIGHT_CYAN}instrumentos-musicales-la-voz${C_RESET}  instrumentos musicales (Nicaragua)\n"
}

# _cmd_specific_help muestra el detalle de UN comando.
_cmd_specific_help() {
    local cmd="$1"
    local description="" usage_args="" example="" notes="" destructive=0

    case "$cmd" in
        start)
            description="Levanta containers YA EXISTENTES via 'docker compose up -d' SIN --build. Útil cuando las imágenes ya están construidas y solo querés reanimar containers parados después de un 'down' o 'stop'. NO rebuilda aunque el Dockerfile haya cambiado (para eso usar 'up'); NO toca volúmenes ni imágenes; deja los containers parados como estaban si el servicio no estaba en el compose actual."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 start dev all                            # todas las dev (3 instancias)
$0 start dev panda-estampados-and-kitsune backend   # solo backend de Panda"
            notes="Diferencias con comandos vecinos:
  up       → rebuilda si Dockerfile/.env cambió y arranca. start NO rebuilda.
  restart  → reinicia containers CORRIENDO sin reanimar los detenidos. start
             levanta todos los que estén definidos, estén arriba o abajo.
Si tocaste backend/Dockerfile, frontend/Dockerfile, docker-compose.yml o
variables de .env, usar 'up' en lugar de 'start' (start no detecta cambios)."
            ;;
        stop)
            description="Detiene containers via 'docker compose stop' pero NO los elimina (preserva el container en estado exited y todas sus imágenes). Los volúmenes named quedan intactos. Para volver a levantarlos: 'start'. Si querés eliminar también los containers: usar 'down'."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 stop dev all                          # detener las 3 dev
$0 stop production mm-bone backend              # solo backend de MM en prod"
            notes="Diferencias:
  stop  → detiene, conserva containers (pueden volver a 'start' sin recrear).
  down  → detiene Y elimina containers; volúmenes persisten.
  nuke  → detiene + elimina containers + elimina volúmenes (DESTRUCTIVO).
Si querés liberar memoria rápido sin perder el estado, 'stop' es lo correcto."
            ;;
        restart)
            description="Reinicia containers CORRIENDO via 'docker compose restart' (sin recrear imágenes, sin reanimar detenidos, sin detectar cambios). Más barato que 'up' cuando solo querés refrescar el estado de un proceso (ej: después de tocar una config que requiere reload de proceso, no de imagen). Si tocaste código que cambia layers de la imagen, usar 'up' o 'build' + 'start'."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 restart dev panda-estampados-and-kitsune backend   # bounce backend de Panda
$0 restart production all caddy                              # bounce Caddy en las 3 prod"
            notes="Diferencias:
  restart → bounce de procesos en containers corriendo (rápido, sin rebuild).
  up      → si hay rebuild pendiente, lo aplica; si no, equivalente a start.
  start   → re-anima containers detenidos también.
No tocar .env/Dockerfile/composer.json entre 'restart' y un nuevo start sin
'up' — restart NO relee configs nuevas."
            ;;
        up)
            description="Equivalente a 'docker compose up -d --build --remove-orphans': construye imágenes (si Dockerfile/compose cambiaron) Y arranca containers en background. Recrea solo los services cuyo compose/config cambió; NO toca volúmenes named. Usar después de un git pull que tocó backend/Dockerfile, frontend/Dockerfile, docker-compose.yml, o variables de .env. Para un rebuild completo desde cero (BD incluida) usar 'nuke' + 'up' o 'fresh'."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 up dev panda-estampados-and-kitsune             # rebuild (si hace falta) + levantar Panda dev
$0 up dev panda-estampados-and-kitsune backend      # rebuild + levantar SOLO backend
$0 up production all                               # las 3 prod (rebuild si cambió algo)"
            notes="Diferencias con comandos vecinos:
  start   → arranca SIN rebuild aunque haya cambios (NO usar tras git pull).
  build   → solo reconstruye imágenes, no arranca nada.
  fresh   → down -v + up --build + migrate + seed (DESTRUCTIVO con BD).
Si solo querés bounce rápido de procesos, 'restart' es más barato que 'up'."
            ;;
        down)
            description="Detiene Y elimina containers via 'docker compose down --remove-orphans' (preserva volúmenes named, imágenes y la build cache). Equivalente a hacer 'docker rm' de los containers del compose. Útil cuando querés un estado limpio de containers pero conservar BD y storage. Para volver a levantar: 'up' o 'start'."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 down dev all                          # bajar las 3 dev (preserva BD)
$0 down dev panda-estampados-and-kitsune postgres    # solo el postgres de Panda"
            notes="Diferencias:
  stop  → detiene containers pero NO los elimina (más rápido de reanudar).
  nuke  → down + ELIMINA VOLÚMENES (BORRA BD). DESTRUCTIVO.
Si vas a tocar docker-compose.yml y querés que docker compose detecte cambios
de services, hacer 'down' antes del 'up' (sino puede quejarse por containers
huérfanos)."
            ;;
        build)
            description="Reconstruye imágenes Docker via 'docker compose build' usando el cache de layers por default (rápido: solo reconstruye stages que cambiaron). El flag 'no-cache' fuerza rebuild completo sin cache, útil cuando el cache quedó stale o cambió una imagen base que no se detecta via checksum. NO arranca containers (solo construye). Para construir Y arrancar en un solo paso: 'up'."
            usage_args="[--no-cache] <stage> <store|all> [<svc>|all]"
            example="Con cache:    $0 build dev all
Sin cache:    $0 build --no-cache dev all
Por svc:      $0 build dev panda-estampados-and-kitsune backend
Prod rebuild: $0 build --no-cache production all"
            notes="Flags:
  no-cache → fuerza 'docker compose build --no-cache' (lento, útil cuando
             el cache tiene una layer fantasma o cambió una imagen base).
El dispatcher acepta 'no-cache' en cualquier posición del comando. Para
construir + arrancar en un solo paso, 'up' es lo correcto."
            ;;
        ps)
            description="Vista rápida de TODOS los containers de las 6 instancias en una sola tabla (sin filtrar por stage/store). Usa 'docker ps --filter label=com.docker.compose.project' y los agrupa por instancia. Ideal para un vistazo general: qué está corriendo, qué se cayó, qué contenedor pertenece a qué tienda. No recibe args posicionales."
            usage_args="(sin args)"
            example="$0 ps
$0 ps | grep -v 'Up '                    # filtrar los NO corriendo"
            notes="Diferencias:
  ps     → todos los containers de las 6 instancias en 1 tabla (este comando).
  status → docker compose ps de 1 instancia específica (stage + store).
  info   → igual que status + columnas extra (puertos, comando, label svc).
Si necesitás saber 'qué contenedores de QUÉ instancia están corriendo', 'ps'
es el comando correcto. Si necesitás detalle de 1 instancia: 'status' o 'info'."
            ;;
        status)
            description="Estado por servicio via 'docker compose ps' para 1 instancia específica (stage + store). Muestra los containers de esa instancia con columnas NAME, COMMAND, SERVICE, STATUS, PORTS. Diferencia con 'ps': 'ps' lista TODOS los containers de las 6 instancias en una sola tabla; 'status' profundiza en una instancia. Diferencia con 'info': 'info' agrega puerto + comando por container."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 status dev all                       # las 3 dev, una por una
$0 status dev panda-estampados-and-kitsune           # 1 instancia
$0 status dev panda-estampados-and-kitsune backend   # solo backend de Panda"
            notes="Diferencias:
  ps     → vista global (todas las instancias, 1 tabla).
  status → vista por instancia (1 instancia, tabla por scope).
  info   → vista por instancia + más columnas (puertos, comando).
Para diagnóstico profundo de 1 instancia, 'info' da más detalle."
            ;;
        info)
            description="Tabla detallada con columnas: instancia (com.docker.compose.project), servicio (com.docker.compose.service), nombre del container, status, puertos publicados y comando del container. Útil para diagnóstico: a simple vista se ve qué container de qué instancia está corriendo qué binario y qué puertos expone al host."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 info dev panda-estampados-and-kitsune             # 1 instancia entera
$0 info production all backend                          # solo backend de las 3 prod
$0 info dev panda-estampados-and-kitsune postgres       # solo postgres de Panda dev"
            notes="Diferencias:
  status → docker compose ps nativo (NAME, COMMAND, SERVICE, STATUS, PORTS).
  info   → docker ps custom con columnas instance + service (labels del compose),
           incluyendo el nombre lógico del servicio, lo que permite distinguir
           containers de distintas instancias con el mismo nombre de servicio.
Si necesitás ver puertos publicados al host para configurar firewall/Caddy,
'info' es el comando correcto."
            ;;
        logs)
            description="Tail de logs via 'docker compose logs -f --tail=50/100'. El servicio es OBLIGATORIO (no hay 'all' implícito para logs porque mergearlos enmascara qué instancia los emite). Si el store es 'all', hace tail paralelo de las 3 instancias del stage, cada una con prefijo [<inst>] para distinguir origen. Ctrl+C para salir."
            usage_args="<stage> <store|all> <servicio>"
            example="$0 logs dev panda-estampados-and-kitsune backend       # tail de 1 instancia
$0 logs dev all backend                                  # tail paralelo de las 3 dev
$0 logs production mm-boutique postgres                  # tail postgres de MM prod"
            notes="El servicio es REQUERIDO (no se permite omitirlo aunque haya
un único servicio en el compose; el dispatcher rechaza 'logs <stage> <store>'
sin servicio). Si store=all, cada línea viene prefijada con [<inst>] para
que sepas de qué instancia viene. Si el container está caído, no hay logs
(usar 'ps' o 'status' primero para confirmar estado)."
            ;;
        volumes)
            description="Lista los volúmenes Docker del scope agrupados por categoría lógica (data / cache / log / other) con tamaño real via 'docker system df -v'. Útil para diagnóstico de espacio en disco y para entender qué volumen corresponde a qué servicio antes de un 'rm-volumes'. Tamaños vía df -v (docker volume inspect devuelve Size='' por default)."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 volumes dev all                         # volúmenes de las 3 dev
$0 volumes production panda-estampados-and-kitsune    # 1 instancia prod
$0 volumes dev panda-estampados-and-kitsune backend    # (filtra por servicio si aplica)"
            notes="Categorías:
  data  → CRÍTICO: postgres_data, storage (Laravel), ftp_data. Si los borrás,
          perdés datos persistentes.
  cache → rebuildable: node_modules, pnpm_store, vite_cache, astro_cache.
          'up' los regenera, safe to nuke.
  log   → logs de PHP-FPM/nginx.
  other → volúmenes sin clasificar.
Para borrar volúmenes: 'rm-volumes' (con confirmación) o 'rm-volumes -y'."
            ;;
        exec)
            description="Ejecuta un comando arbitrario dentro de un container via 'docker compose exec'. Útil para debugging (bash en backend), correr artisan sin pasar por el wrapper (migrate, tinker, queue:work), inspeccionar el filesystem del container (ls /var/www), o conectarse a postgres via psql. El comando y sus args se pasan tal cual al entrypoint del container."
            usage_args="<stage> <store> <servicio> <comando...>"
            example="bash en backend:        $0 exec dev panda-estampados-and-kitsune backend bash
artisan tinker:          $0 exec dev panda-estampados-and-kitsune backend /usr/local/bin/php artisan tinker
psql al postgres:        $0 exec dev panda-estampados-and-kitsune postgres /usr/bin/psql -U postgres
cat de un archivo:       $0 exec production mm-boutique backend /bin/cat /var/www/html/.env"
            notes="El servicio es OBLIGATORIO (no hay 'all' implícito en exec).
El comando se ejecuta SIN TTY (-T implícito cuando viene del dispatcher),
así que programas interactivos tipo 'vim' o 'top' no van a funcionar bien;
usar 'bash' para inspección interactiva.
Diferencias con 'seed-db --sql'/'db-restore': exec NO toca la BD; es la
misma diferencia que entre 'docker exec psql' y un wrapper de carga."
            ;;
        setup)
            description="Primera instalación COMPLETA de una instancia (o todas del stage con store=all). Crea docker/versions.env si falta, asegura .env de cada instancia, levanta containers, rellena credenciales vacias con defaults de dev (FTP_USERNAME/FTP_PASSWORD/POSTGRES_PASSWORD), detecta hosts faltantes en /etc/hosts y los agrega automaticamente con sudo -n (o muestra el comando manual si no hay sudo), y luego corre el bootstrap del backend en orden: storage:link → cache:clear + config:clear + view:clear → assets:sync-from-public → migrate --force → tenants:bootstrap → key:generate (via cmd_bootstrap). Después de setup la instancia está lista para recibir tráfico."
            usage_args="<stage> <store|all>"
            example="$0 setup dev panda-estampados-and-kitsune           # 1 instancia dev
$0 setup dev all                                   # las 3 dev
$0 setup production all                            # las 3 prod"
            notes="Si sudo no esta cacheado, setup detecta hosts faltantes y muestra el bloque a pegar con 'sudo tee -a /etc/hosts'. El usuario puede correr 'sudo -v' antes de setup para que el setup agrege los hosts automaticamente (sin tener que copiarlos a mano).

Despues de setup, pasos manuales para dev:
  1. (opcional) sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy
  2. Acceder via http://<store>.test:5321 (frontend dev) — si el setup no pudo agregar el host, agregarlo con:
     ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts

Diferencias:
  setup     → de cero: .env + credenciales + up + storage:link + caches + migrate +
              tenants:bootstrap + key:generate. Pensado para la 1ra vez.
  bootstrap → solo regenera APP_KEY (idempotente, una sola responsabilidad).
  fresh     → de cero PERO también BORRA volúmenes (DESTRUCTIVO).
Si la instancia ya tiene datos y querés agregar un service nuevo al compose,
NO usar 'setup' (no toca datos existentes) — usar 'up'."
            ;;
        bootstrap)
            description="Regenera la APP_KEY del backend Laravel si está vacía o marcada con placeholder ('GENERAR_CON_KEY' o 'GENERATE'). Idempotente: si APP_KEY ya tiene un valor real, no hace nada y sale con [ok]. NO hace storage:link, cache:clear, migrate, ni tenants:bootstrap (esos los hace 'setup'). Comando de UNA SOLA RESPONSABILIDAD: regenerar APP_KEY. Se puede correr múltiples veces sin efectos secundarios."
            usage_args="<stage> <store>"
            example="$0 bootstrap dev panda-estampados-and-kitsune      # APP_KEY de Panda dev
$0 bootstrap production mm-boutique                     # APP_KEY de MM prod"
            notes="Diferencias:
  bootstrap → SOLO regenera APP_KEY si falta o es placeholder (idempotente).
  setup     → bootstrap completo: storage:link, chown, caches, migrate,
              tenants:bootstrap + bootstrap al final.
  seed-db   → migrate + (--seed y/o --sql). Foco en contenido de BD.
Si solo querés arreglar APP_KEY sin tocar nada más, 'bootstrap' es el comando
correcto. No acepta flags — si querés seeders usar 'seed-db --seed'."
            ;;
        db-backup)
            description="Exporta la BD del tenant actual a un archivo .sql.gz en db-backups/ con naming <stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz. Por default comprime con gzip (--no-compress genera .sql plano). Soporta --schema-only (solo DDL, útil para fixtures) y --output <path> (path custom relativo a db-backups/ o absoluto). NO tocar la BD: es solo lectura."
            usage_args="<stage> <store> [--no-compress] [--schema-only] [--output <path>]"
            example="$0 db-backup dev panda-estampados-and-kitsune                    # default: db-backups/dev-panda-...-ts.sql.gz
$0 db-backup production mm-boutique --schema-only        # solo DDL, sin data
$0 db-backup dev panda-estampados-and-kitsune --output db-backups/manual.sql
$0 db-backup dev all                                       # backup de las 3 dev (1 archivo por instancia)"
            notes="Output:
  default → db-backups/<stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz
  custom  → path relativo a db-backups/ o absoluto.
NO commitear el dump a git (puede contener PII). Compartir via Drive/S3/Slack.
Para cargar el dump: 'db-restore <stage> <store> <archivo>' (con pre-backup
safety net automático)."
            ;;
        db-restore)
            description="Carga un dump .sql o .sql.gz en la BD del tenant actual via psql. SIEMPRE hace un backup pre-restore (safety net) antes de pisar — salvo --no-pre-backup. Por default corre 'migrate --force' después (por si el dump está desactualizado respecto al schema) — salvo --skip-migrate. --truncate hace DROP SCHEMA + CREATE SCHEMA antes (más limpio que el --clean del dump). DESTRUCTIVO: pisa la BD actual. Confirmación interactiva salvo --force."
            usage_args="<stage> <store> <archivo> [--force] [--no-pre-backup] [--skip-migrate] [--truncate]"
            example="$0 db-restore dev panda-estampados-and-kitsune \
    dev-panda-estampados-and-kitsune-2026-08-26-1430.sql.gz
$0 db-restore production mm-boutique mm-boutique.sql.gz --force
$0 db-restore dev all backup.sql.gz --truncate           # DROP SCHEMA antes"
            destructive=1
            notes="Flags:
  --force          → no pedir confirmación interactiva.
  --no-pre-backup  → saltar el safety net (no recomendado, salvo testing).
  --skip-migrate   → no correr migrate después (útil si el dump ya está al día).
  --truncate       → DROP SCHEMA public + CREATE SCHEMA public antes del load.
Resolución del archivo:
  1. Path absoluto: se usa tal cual.
  2. Relativo: primero se busca en cwd, luego en db-backups/, luego en repo root.
Pre-backup queda en db-backups/pre-restore-<stage>-<store>-<ts>.sql.gz para
rollback si el restore sale mal."
            ;;
        seed-db)
            description="Migra y popula la BD del tenant actual. Por default corre 'php artisan migrate --force' (siempre). NO toca key:generate, storage:link ni caches — foco exclusivo en contenido de BD. Con --seed corre db:seed después del migrate (datos fake de testing). Con --sql <archivo> carga un dump .sql/.sql.gz después del migrate (datos reales o fixtures). --seed y --sql pueden combinarse: el orden es migrate → seed → sql."
            usage_args="<stage> <store> [--seed] [--sql <archivo>]"
            example="Solo migrate:           $0 seed-db dev panda-estampados-and-kitsune
Migrate + seeders:       $0 seed-db dev panda-estampados-and-kitsune --seed
Migrate + SQL:           $0 seed-db dev panda-estampados-and-kitsune --sql fixtures.sql
Migrate + seed + SQL:    $0 seed-db dev panda-estampados-and-kitsune --seed --sql dump.sql
Prod (sin tocar data):  $0 seed-db production mm-boutique"
            notes="Diferencias:
  bootstrap   → APP_KEY (idempotente, sin tocar BD).
  setup       → setup completo de primera instalación (incluye migrate + tenants:bootstrap).
  seed-db     → migrate + (--seed | --sql). Solo contenido de BD.
  db-restore  → carga dump + pre-backup safety net (DESTRUCTIVO).
  fresh       → down -v + rebuild + migrate + seed + tenants (DESTRUCTIVO).
Resolución del --sql: primero cwd, luego db-backups/. Si el archivo termina
en .gz se descomprime on-the-fly via gunzip."
            ;;
        sync-assets)
            description="Sincroniza los assets de marca commiteados en brand-assets/store/{slug}/ (logos, favicons) y brand-assets/shared/ (iconos sociales) al FTP del tenant via 'php artisan assets:sync-from-public'. Por default NO sobrescribe archivos existentes en el FTP; con --force sí. --dry-run muestra qué archivos se subirían sin escribir nada. Convenciones documentadas en docs/architecture/assets-naming-convention.md."
            usage_args="<stage> <store|all> [--dry-run] [--force]"
            example="$0 sync-assets dev panda-estampados-and-kitsune              # 1 tienda, sin overwrite
$0 sync-assets dev all                                      # 3 tiendas
$0 sync-assets dev all --dry-run                            # preview sin escribir
$0 sync-assets production mm-boutique --force               # sobrescribir en FTP"
            notes="Comportamiento con store=all:
  Todos los backends del stage comparten el mismo brand-assets/ via bind
  mount (ver docker-compose.yml), así que llamar al artisan command sin
  --store sincroniza TODAS las tiendas. El wrapper usa la primera instancia
  del scope solo como 'entry point' — el resultado en FTP es independiente
  de qué backend se use.
Si querés sincronizar UNA sola tienda, pasala como store específico
(no 'all'). El wrapper traduce eso a --store=<slug> para el artisan command."
            ;;
        nuke)
            description="DESTRUCTIVO: baja containers Y elimina volúmenes via 'docker compose down --volumes --remove-orphans' (o 'docker compose rm -f -s -v' por servicio). Borra BD completa, storage de Laravel, caches, FTP uploaded files. Pide confirmación escribiendo 'yes' antes de proceder. NO se puede deshacer. Útil antes de un 'fresh' o cuando la BD quedó corrupta."
            usage_args="<stage> <store|all> [<svc>|all]"
            example="$0 nuke dev panda-estampados-and-kitsune            # 1 instancia dev (containers + volúmenes)
$0 nuke dev all                                       # las 3 dev
$0 nuke production all                                # ⚠ las 3 prod (BORRA BD)"
            destructive=1
            notes="Diferencias con destructivos vecinos:
  nuke       → down + volúmenes en 1 paso. Containers YA NO existen al final.
  rm-volumes → solo volúmenes; containers SIGUEN corriendo (útil para empezar
               con BD vacía sin perder imágenes construidas).
  fresh      → nuke + up --build + migrate + seed + tenants (clean slate
               completo). nuke es solo el paso 1/4 de fresh.
ANTES de nuke en producción: SIEMPRE 'db-backup' primero. Sin dump, los
datos desaparecen para siempre."
            ;;
        rm-volumes)
            description="DESTRUCTIVO: enumera los volúmenes Docker declarados en el compose de cada instancia del scope y los borra uno por uno via 'docker volume rm'. NO toca containers (siguen corriendo con sus imágenes, solo pierden acceso a los volúmenes). Borra datos persistentes: BD, storage Laravel, caches, FTP uploaded files. Pide confirmación escribiendo 'yes' salvo -y (skip)."
            usage_args="[-y] <stage> <store|all> [<svc>|all]"
            example="$0 rm-volumes -y dev panda-estampados-and-kitsune    # 1 instancia dev, skip confirm
$0 rm-volumes dev all                                 # las 3 dev, pedir confirm
$0 rm-volumes production mm-boutique postgres         # solo volumen postgres de MM"
            destructive=1
            notes="Diferencias con destructivos vecinos:
  nuke       → rm-volumes + down containers (estado limpio total).
  rm-volumes → SOLO volúmenes, containers quedan corriendo (sin BD pero
               con imágenes; útil si querés 'migrate from scratch' sin
               re-buildear).
  fresh      → rm-volumes + down + up --build + migrate + seed (DESTRUCTIVO).
Flag -y: el dispatcher lo lee de $RM_VOLUMES_SKIP_CONFIRM (1 si está).
Si store=all, se borran los volúmenes de las 3 tiendas del stage (9+ vols)."
            ;;
        fresh)
            description="DESTRUCTIVO: clean slate de UNA instancia. Ejecuta down -v + up --build + pre-initdb workaround (para imágenes postgis/postgis con compose override que bypasea el entrypoint) + migrate --force + db:seed --force + tenants:bootstrap con el seed JSON del instance. NO acepta store=all (debe ser 1 tienda específica). Después de fresh la instancia está lista con datos base."
            usage_args="<stage> <store>"
            example="$0 fresh dev panda-estampados-and-kitsune           # clean slate Panda dev
$0 fresh production mm-boutique                       # clean slate MM prod"
            destructive=1
            notes="Pre-initdb workaround:
  El compose dev/prod tiene un override 'command:' que bypasea el
  docker-entrypoint.sh de postgis/postgis, que es el que corre initdb.
  En un volume fresh, postgres no puede arrancar. Por eso el pre-initdb
  se hace manualmente via 'docker compose run --rm --entrypoint initdb'.
  Si la imagen NO es postgis/postgis, este paso se skipea (asume entrypoint
  oficial).
Diferencias:
  nuke     → solo down -v (paso 1/3 de fresh; el resto lo hace fresh).
  rm-volumes → solo volúmenes, no toca containers (fresh es más completo).
  fresh    → down -v + up --build + initdb + migrate + seed + tenants.
             Es el 'reset to factory' de una instancia específica."
            ;;
        hosts)
            description="Imprime el bloque a agregar al final de /etc/hosts para resolución local de los dominios de las 6 instancias (3 prod + 3 dev + subdominios www/admin). No recibe args. Uso típico: '$0 hosts | sudo tee -a /etc/hosts'. Después de hosts podés navegar a http://<store>.com (dev: puerto 5321; prod: 4321) sin tocar DNS."
            usage_args="(sin args)"
            example="$0 hosts | sudo tee -a /etc/hosts"
            notes="El bloque imprime 12 líneas (6 dominios × 2 subdominios: el base,
www y admin). NO es destructivo: solo imprime. NO requiere docker corriendo.
Diferencia con 'caddy': 'caddy' imprime el Caddyfile (config de reverse proxy
para enrutar las 6 instancias), 'hosts' imprime el /etc/hosts (resolución
local de nombres). En dev normalmente alcanza con hosts; en prod se usa Caddy."
            ;;
        caddy)
            description="Imprime el contenido de docker/caddy/Caddyfile (config de Caddy para enrutar las 6 instancias: 3 prod + 3 dev, con sus dominios www/admin). No recibe args. Uso típico: '$0 caddy | sudo tee /etc/caddy/Caddyfile && sudo systemctl restart caddy'. El archivo fuente vive en el repo y se versiona en git."
            usage_args="(sin args)"
            example="$0 caddy | sudo tee /etc/caddy/Caddyfile && sudo systemctl restart caddy"
            notes="Diferencia con 'hosts': 'hosts' resuelve nombres LOCALMENTE
(/etc/hosts, para dev); 'caddy' configura el reverse proxy que enruta los
mismos dominios a las instancias correctas vía Docker network (para prod).
En dev se usa /etc/hosts + acceder por puerto directo (5321 dev / 4321 prod);
en prod se usa Caddy en :80/:443 que termina TLS y rutea al container."
            ;;
        help)
            cmd_help
            return 0
            ;;
        *)
            printf "${C_BRIGHT_RED}Comando desconocido:${C_RESET} %s\n\n" "$cmd"
            printf "Comandos válidos: %s ${C_BRIGHT_MAGENTA}help${C_RESET} para ver el índice\n" "$0"
            return 1
            ;;
    esac

    # ─── Render del help específico ───────────────────────────────
    local cmd_color="${C_BRIGHT_MAGENTA}"
    if (( destructive == 1 )); then
        cmd_color="${C_BRIGHT_RED}"
    fi

    printf "\n"
    printf "${cmd_color}%s${C_RESET}  %s\n" "$cmd" "$description"
    printf "\n"
    printf "${C_BOLD}Uso${C_RESET}\n"
    printf "  %s ${cmd_color}%s${C_RESET} %s\n" "$0" "$cmd" "$usage_args"
    printf "\n"
    printf "${C_BOLD}Ejemplos${C_RESET}\n"
    if [[ -n "$example" ]]; then
        while IFS= read -r line; do
            printf "  ${C_DIM}%s${C_RESET}\n" "$line"
        done <<< "$example"
    fi
    if [[ -n "$notes" ]]; then
        printf "\n"
        printf "${C_BOLD}Notas${C_RESET}\n"
        # Iterar per-line (mismo patrón que Ejemplos) para soportar
        # notes multi-línea: cada línea con indent + dim.
        while IFS= read -r line; do
            printf "  ${C_DIM}%s${C_RESET}\n" "$line"
        done <<< "$notes"
    fi
    if (( destructive == 1 )); then
        printf "\n"
        printf "${C_BRIGHT_RED}Comando destructivo:${C_RESET} borra datos sin rollback automatico.\n"
    fi
    printf "\n"
}

# ─────────────────────────────────────────────────────────────────
# Helpers de formato.
# ─────────────────────────────────────────────────────────────────

# _ansi_strip quita códigos ANSI del stdin (lee del pipe, no del here-string).
# Esencial para que el cálculo de anchos de columna NO cuente los bytes
# de escape ANSI cuando el caller pasa un valor coloreado.
_ansi_strip() {
    /usr/bin/sed -r "s/\x1B\[[0-9;]*[A-Za-z]//g"
}

# _visible_len devuelve la longitud VISIBLE de un string en CHARS
# (no bytes). Crítico: wc -c cuenta BYTES (ó = 2 bytes UTF-8) y awk
# length() también cuenta bytes para multibyte. Usamos bash nativo
# ${#var} que cuenta chars Unicode correctamente.
_visible_len() {
    local stripped
    stripped=$(printf '%s' "$1" | _ansi_strip)
    echo "${#stripped}"
}

# _box_draw dibuja una tabla Markdown-style (|---|---|) con bordes
# generados por printf (no csvlook, porque csvlook no soporta ANSI
# en celdas — contaría los bytes de escape y desalinearía).
#
# Ancho de cada columna = max(len_visible(header), max(len_visible(value))).
# Los códigos ANSI en los valores se cuentan solo como su longitud
# visible para el cálculo de anchos; al imprimir, se emiten enteros.
#
# La primera columna (índice 0) se pinta con $_BOX_CMD_COLOR si está
# definida (magenta por default). Para tablas destructivas, setear
# _BOX_CMD_COLOR="${C_BRIGHT_RED}" antes de llamar.
#
# Args: n_col  header1 header2 ... headerN  data1_1 ... dataN_M
#
# Ejemplo:
#   _box_draw 3 "Cmd" "Stage" "Desc" \
#             "start" "dev" "Levantar" \
#             "stop"  "dev" "Detener"
_box_draw() {
    local n_cols="${1:-0}"
    shift
    local total_args=${#@}
    if (( n_cols <= 0 )) || (( total_args == 0 )); then
        return 0
    fi
    if (( total_args % n_cols != 0 )); then
        log_err "_box_draw: args ($total_args) no múltiplo de cols ($n_cols)"
        return 1
    fi

    local -a all_args=("$@")
    local -a headers=("${all_args[@]:0:n_cols}")
    local -a rows=("${all_args[@]:n_cols}")

    local cmd_color="${_BOX_CMD_COLOR:-${C_BRIGHT_MAGENTA}}"

    # Calcular anchos por columna basándose en la longitud VISIBLE.
    local -a widths
    local c r vw
    for ((c=0; c<n_cols; c++)); do
        local w
        w=$(_visible_len "${headers[c]}")
        for ((r=c; r<${#rows[@]}; r+=n_cols)); do
            vw=$(_visible_len "${rows[r]}")
            (( vw > w )) && w=$vw
        done
        widths+=("$w")
    done

    # Construir bordes.
    #   top:    +-------+-------+-------+
    #   mid:    +-------+-------+-------+     (separador entre header y datos)
    #   bot:    +-------+-------+-------+
    local top_border="" mid_border="" bot_border=""
    for ((c=0; c<n_cols; c++)); do
        local seg
        seg=$(printf -- '-%.0s' $(/usr/bin/seq 1 $((widths[c]+2))))
        top_border+="+${seg}"
        mid_border+="+${seg}"
        bot_border+="+${seg}"
    done
    top_border="${top_border}+"
    mid_border="${mid_border}+"
    bot_border="${bot_border}+"

    # Top.
    printf "%s\n" "$top_border"

    # Header.
    printf "|"
    for ((c=0; c<n_cols; c++)); do
        printf " ${C_BOLD}%-${widths[c]}s${C_RESET} |" "${headers[c]}"
    done
    printf "\n"

    # Mid (separator).
    printf "%s\n" "$mid_border"

    # Filas.
    local n_rows=$(( ${#rows[@]} / n_cols ))
    local off
    for ((r=0; r<n_rows; r++)); do
        off=$((r * n_cols))
        printf "|"
        # Columna 0 con color.
        printf " ${cmd_color}%-${widths[0]}s${C_RESET} |" "${rows[off]}"
        # Resto sin color.
        for ((c=1; c<n_cols; c++)); do
            printf " %-${widths[c]}s |" "${rows[off+c]}"
        done
        printf "\n"
    done

    # Bottom.
    printf "%s\n" "$bot_border"
    printf "\n"
}
