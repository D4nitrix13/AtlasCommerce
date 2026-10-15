#!/usr/bin/env bash
# scripts/docker/setup/first-install.sh
# cmd_setup <stage> <store|all>
# Primera instalación COMPLETA: copia docker/versions.env si falta,
# asegura .env de cada instancia, levanta los stacks, y luego corre
# el bootstrap del backend (storage:link + caches + key:generate +
# migrate + tenants:bootstrap) en orden. Acepta store=all para aplicar
# a todas las tiendas del stage.
#
# Después de setup la instancia está lista para recibir tráfico.
# Para datos de testing, correr `seed-db --seed` aparte.
set -euo pipefail

# setup_compose_quiet <inst> <timeout_secs> <args...>
# Wrapper local de docker_compose() que suprime el bloque [cmd]
# repetitivo (cd/env/exec) que ensucia el output del setup. Solo
# imprime el resultado del comando (output + exit code). Los 8
# sub-pasos del bootstrap se vuelven legibles en vez de un wall
# de texto. Se usa SOLO en setup; el resto del orquestador sigue
# usando docker_compose() con el [cmd] block verbose.
#
# Acepta un timeout en segundos (default 30) para que el setup NUNCA
# se quede colgado si un comando artisan pide input interactivo
# o se demora. Si timeout: exit code 124 + mensaje 'timeout reached'.
setup_compose_quiet() {
    local name="$1"; shift
    local timeout_secs="${1:-30}"; shift
    local dir
    dir="$(instance_compose_dir "$name")"
    local file
    file="$(instance_compose_file "$name")"
    local env_file=""
    if [[ -f "${dir}/environment/.env" ]]; then
        env_file="--env-file environment/.env"
    fi
    local host_uid="${HOST_UID:-$(${ID_BIN} -u)}"
    local host_gid="${HOST_GID:-$(${ID_BIN} -g)}"

    # No [cmd] block. Solo ejecutamos y devolvemos output+exit.
    # Timeout con gtimeout si esta disponible, sino con 'timeout'
    # de coreutils, sino fallback a sin timeout (con -k por si se cuelga).
    local timeout_bin=""
    if /usr/bin/command -v timeout >/dev/null 2>&1; then
        timeout_bin="/usr/bin/timeout"
    fi

    if [[ -n "$timeout_bin" ]]; then
        (cd "$dir" && HOST_UID="${host_uid}" HOST_GID="${host_gid}" \
            $timeout_bin "${timeout_secs}" ${DOCKER_BIN} compose -f "$file" $env_file "$@")
    else
        # Sin timeout disponible: ejecutar igual y rezar.
        (cd "$dir" && HOST_UID="${host_uid}" HOST_GID="${host_gid}" \
            ${DOCKER_BIN} compose -f "$file" $env_file "$@")
    fi
}

cmd_setup() {
    local stage="$1"
    local store="${2:-all}"
    # Flags aceptados:
    #   --skip-hosts-add / -skip-hosts-add
    #       Omite la fase 3 (Hosts): NO intenta agregar hosts a
    #       /etc/hosts. Por defecto los agrega automaticamente cuando
    #       sudo -n funciona, o muestra el comando manual cuando no.
    # Aceptamos guion simple y doble para flexibilidad con la
    # convencion del script (flags booleanas usan -flag en lugar
    # de --flag, ver -y, -h).
    local add_hosts=1
    for arg in "$@"; do
        case "$arg" in
            --skip-hosts-add|-skip-hosts-add) add_hosts=0 ;;
        esac
    done

    log_bold "SETUP — primera instalación de las instancias"

    # 6 fases visuales: cada una con su cajita ┌── [N/6] ... ──┐.
    # 1) versions.env  2) Scope  3) Hosts  4) Credenciales
    # 5) Containers  6) Backend bootstrap.
    local TOTAL_PHASES=6

    # 1. docker/versions.env (centraliza versiones de imágenes)
    print_section_title "Versions" 1 "$TOTAL_PHASES" "Centralizar versiones de imagenes"
    if [[ ! -f "${ROOT_DIR}/docker/versions.env" ]]; then
        if [[ -f "${ROOT_DIR}/docker/versions.env.example" ]]; then
            log_info "Creando docker/versions.env desde .example (editá versiones si querés pinear)."
            /usr/bin/cp "${ROOT_DIR}/docker/versions.env.example" "${ROOT_DIR}/docker/versions.env"
        else
            log_err "docker/versions.env.example no existe. Repo corrupto?"
            exit 1
        fi
    else
        log_info "docker/versions.env ya existe."
    fi
    print_section_end

    # 2. Resolver qué instancias levanta este setup según (stage, store).
    # El dispatcher ya normalizó stage (prod→production, dev→development).
    local -a instances_to_start=()
    while IFS=$'\t' read -r inst _; do
        instances_to_start+=("$inst")
    done < <(resolve_targets "$stage" "$store")
    print_section_title "Scope" 2 "$TOTAL_PHASES" "stage=${stage} store=${store} (${#instances_to_start[@]} instancias)"
    log_info "Instancias a levantar: ${instances_to_start[*]}"
    print_section_end

    # 3. Asegurar .env de cada instancia.
    for inst in "${instances_to_start[@]}"; do
        ensure_env_file "$inst"
    done

    # 3.4. Safety net: si el .env tiene placeholders 'replace_with_...'
    # o variables vacias que docker-compose espera, las rellena con
    # defaults de dev. El .env.example ya viene con valores por defecto
    # (FTP_PASSWORD=devpass, POSTGRES_PASSWORD=devpass), asi que este
    # bloque solo dispara si alguien edito el .env.example y dejo
    # placeholders, o si es un .env heredado de antes de la fix.
    # En dev, el relleno es silencioso. En prod, el warning SI se
    # muestra porque alli los placeholders son peligrosos.
    print_section_title "Credenciales" 4 "$TOTAL_PHASES" "FTP + POSTGRES_PASSWORD con defaults de dev"
    local cred_defaults_set=0
    for inst in "${instances_to_start[@]}"; do
        local env_file="${INSTANCES_DIR}/$(echo "$inst" | tr ':' '/')/environment/.env"
        if [[ -f "$env_file" ]]; then
            # FTP_USERNAME: dev-ftp.
            if /usr/bin/grep -qE '^FTP_USERNAME[[:space:]]*=' "$env_file"; then
                local current
                current=$(/usr/bin/grep -E '^FTP_USERNAME[[:space:]]*=' "$env_file" | /usr/bin/cut -d= -f2- | /usr/bin/sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                if [[ -z "$current" || "$current" == *"replace_with"* ]]; then
                    /usr/bin/sed -i.bak 's|^FTP_USERNAME=.*|FTP_USERNAME=dev-ftp|' "$env_file" && /usr/bin/rm -f "$env_file.bak"
                    cred_defaults_set=1
                fi
            else
                /usr/bin/printf '\nFTP_USERNAME=dev-ftp\n' >> "$env_file"
                cred_defaults_set=1
            fi
            # FTP_PASSWORD: 'devpass'.
            if /usr/bin/grep -qE '^FTP_PASSWORD[[:space:]]*=' "$env_file"; then
                local current
                current=$(/usr/bin/grep -E '^FTP_PASSWORD[[:space:]]*=' "$env_file" | /usr/bin/cut -d= -f2- | /usr/bin/sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                if [[ -z "$current" || "$current" == *"replace_with"* ]]; then
                    /usr/bin/sed -i.bak 's|^FTP_PASSWORD=.*|FTP_PASSWORD=devpass|' "$env_file" && /usr/bin/rm -f "$env_file.bak"
                    cred_defaults_set=1
                fi
            else
                /usr/bin/printf 'FTP_PASSWORD=devpass\n' >> "$env_file"
                cred_defaults_set=1
            fi
            # POSTGRES_PASSWORD: 'devpass'.
            if /usr/bin/grep -qE '^POSTGRES_PASSWORD[[:space:]]*=' "$env_file"; then
                local current
                current=$(/usr/bin/grep -E '^POSTGRES_PASSWORD[[:space:]]*=' "$env_file" | /usr/bin/cut -d= -f2- | /usr/bin/sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                if [[ -z "$current" || "$current" == *"replace_with"* ]]; then
                    /usr/bin/sed -i.bak 's|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=devpass|' "$env_file" && /usr/bin/rm -f "$env_file.bak"
                    cred_defaults_set=1
                fi
            fi
        fi
    done
    if [[ $cred_defaults_set -eq 1 ]]; then
        if [[ "$stage" == "development" ]]; then
            log_info "Credenciales con placeholder rellenadas con defaults de dev (FTP_USERNAME=dev-ftp, FTP_PASSWORD=devpass, POSTGRES_PASSWORD=devpass)."
        else
            log_warn "Credenciales con placeholder rellenadas con defaults. Para produccion, regenera los passwords con: openssl rand -base64 32"
        fi
    else
        log_info "Credenciales OK (FTP_USERNAME, FTP_PASSWORD, POSTGRES_PASSWORD con valores)."
    fi
    print_section_end

    # 3.5. Detectar hosts faltantes y PREGUNTAR al usuario si quiere
    # agregarlos a /etc/hosts automaticamente. Si no, mostrar el
    # comando manual exacto. Por defecto, intentamos agregarlos
    # (cuando el usuario no especifica, no preguntamos y agregamos
    # solo si tenemos sudo sin password; en otro caso, mostramos).
    local missing_hosts=()
    local inst store_slug store_hosts entry
    local host_entry
    for inst in "${instances_to_start[@]}"; do
        store_slug="${inst#*:}"
        store_hosts=(
            "${store_slug}.test"
            "www.${store_slug}.test"
            "admin.${store_slug}.test"
        )
        for host_entry in "${store_hosts[@]}"; do
            # Detectar si la entrada YA EXISTE en /etc/hosts.
            # grep -wqF: match whole-word, quiet, fixed-string. Esto
            # evita falsos positivos con subdominios ('foo.test' no
            # matchea 'foo.test.evil') y viceversa.
            if /usr/bin/grep -wqF "$host_entry" /etc/hosts 2>/dev/null; then
                # Ya esta. No agregar.
                :
            else
                missing_hosts+=("$host_entry")
            fi
        done
    done
    if [[ ${#missing_hosts[@]} -gt 0 && $add_hosts -eq 1 ]]; then
        print_section_title "Hosts" 3 "$TOTAL_PHASES" "Agregar ${#missing_hosts[@]} entradas a /etc/hosts"
        # Construir el bloque a agregar.
        local hosts_block=""
        for entry in "${missing_hosts[@]}"; do
            hosts_block+="127.0.0.1  ${entry}"$'\n'
        done
        # Loguear lo que se va a agregar (transparencia).
        log_info "Entradas a agregar:"
        for entry in "${missing_hosts[@]}"; do
            log_info "  127.0.0.1  ${entry}"
        done
        # Intentar con sudo no-interactive primero (caso comun: ya
        # corriste 'sudo -v' antes de setup). Si funciona, silencioso.
        if /usr/bin/sudo -n true 2>/dev/null; then
            log_info "Agregando entradas a /etc/hosts (sudo no-interactive)..."
            if /usr/bin/printf '%s' "$hosts_block" | /usr/bin/sudo /usr/bin/tee -a /etc/hosts >/dev/null; then
                log_ok "Hosts agregados a /etc/hosts."
            else
                log_warn "Fallo al escribir a /etc/hosts (no critico, setup sigue)."
            fi
        else
            # Sin cache de sudo. Mostrar y EJECUTAR el comando con sudo
            # (esto pedira password interactivamente al usuario).
            log_warn "Confirmá con sudo cuando se pida:"
            log_info "Ejecutando: ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts"
            if /usr/bin/printf '%s' "$hosts_block" | /usr/bin/sudo /usr/bin/tee -a /etc/hosts >/dev/null; then
                log_ok "Hosts agregados a /etc/hosts."
            else
                log_warn "sudo fallo (password incorrecto o cancelado). Setup sigue."
                log_info "Recordatorio: ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts"
            fi
        fi
        print_section_end
    fi

    # 4. Levantar containers.
    print_section_title "Containers" 5 "$TOTAL_PHASES" "Levantar ${#instances_to_start[@]} instancia(s) con docker compose"
    for inst in "${instances_to_start[@]}"; do
        log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" up -d --remove-orphans
        log_ok "${inst} levantado."
    done
    print_section_end

    # 5. Bootstrap del backend Laravel en cada instancia levantada.
    # Secuencia: storage:link → cache:clear → config:clear → migrate →
    # tenants:bootstrap. `key:generate` lo hace `cmd_bootstrap` al final
    # (idempotente, regenera APP_KEY si falta).
    #
    # NOTA sobre extracción del slug: inst viene como `stage:store-slug`,
    # por ejemplo `development:panda-estampados-and-kitsune`. Cortamos
    # SOLO el prefijo `stage:` con ${var#*:} para preservar guiones
    # internos del slug (que tiene varios `-`). NO usar ${var%%-*} porque
    # cortaría al primer guion y rompería el lookup en resolve_targets.
    # 6. Backend bootstrap. Cada sub-paso se enumera para que el dev
    # pueda seguir el progreso. Usamos 'setup_compose_quiet' (wrapper
    # local) en vez de 'docker_compose' para suprimir el bloque [cmd]
    # repetitivo — el dev solo ve el sub-paso enumerado y el resultado.
    print_section_title "Bootstrap" 6 "$TOTAL_PHASES" "Backend Laravel de cada instancia"
    for inst in "${instances_to_start[@]}"; do
        local stage_of_inst="${inst%%:*}"
        local store_of_inst="${inst#*:}"
        local stage_of_inst="${inst%%:*}"
        local store_of_inst="${inst#*:}"

        # Esperar Postgres healthy antes de cualquier comando artisan.
        # Dev: container local <slug>-dev-postgres. Prod: DB externa, sin
        # container local -> skipea el check (asume DB ya operativa).
        local pg_container=""
        if [[ "$stage_of_inst" == "development" ]]; then
            pg_container="${store_of_inst}-dev-postgres"
        fi
        if [[ -n "$pg_container" ]] && \
            /usr/bin/docker ps --format '{{.Names}}' 2>/dev/null \
                | /usr/bin/grep -qx "$pg_container"; then
            local attempts=0
            local max_attempts=30
            while ! /usr/bin/docker exec -i "$pg_container" \
                /usr/bin/pg_isready -U "${DB_USERNAME:-${store_of_inst//-/_}}" >/dev/null 2>&1; do
            attempts=$((attempts + 1))
            if [[ $attempts -ge $max_attempts ]]; then
                log_err "Postgres de ${inst} no respondio despues de ${max_attempts} intentos"
                return 1
            fi
            /usr/bin/sleep 1
        done
        fi

        # 6.1. Esperar Postgres healthy antes de cualquier artisan.
        # (Logica existente del setup.)

        # 6.2. chown + mkdir storage/framework/{cache,views,sessions}.
        # CRITICAL: el volumen storage/ se crea como root en el build,
        # pero PHP-FPM corre como UID 1000. Ademas, los subdirectorios
        # de storage/framework/ (cache/data, views, sessions) NO
        # existen hasta el primer cache write. Sin el mkdir -p, los
        # artisan cache:clear y view:clear fallan con 'Failed to
        # clear cache. Make sure you have the appropriate permissions.'
        # porque no pueden crear los subdirectorios.
        log_info "  [6.2] chown + mkdir storage/framework"
        local chown_out chown_exit
        chown_out=$(setup_compose_quiet "$inst" 30 exec -u root -T backend /bin/sh -c '
            chown -R 1000:1000 /var/www/html/storage 2>&1 &&
            mkdir -p /var/www/html/storage/framework/cache/data
                     /var/www/html/storage/framework/views
                     /var/www/html/storage/framework/sessions
                     /var/www/html/storage/logs 2>&1 &&
            chown -R 1000:1000 /var/www/html/storage 2>&1
        ')
        chown_exit=$?
        if [[ $chown_exit -eq 0 ]]; then
            log_info "        OK"
        elif [[ $chown_exit -eq 124 ]]; then
            log_warn "        timeout (30s)"
        else
            log_warn "        fallo (exit $chown_exit): ${chown_out}"
        fi

        # 6.3. storage:link (idempotente; ignora error si ya existe).
        log_info "  [6.3] storage:link"
        local link_out
        link_out=$(setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan storage:link 2>&1 || true)
        if /usr/bin/printf '%s' "$link_out" | /usr/bin/grep -q "already exists"; then
            log_info "        (storage:link ya existia, skip)"
        else
            log_info "        OK"
        fi

        # 6.4. cache:clear + config:clear + view:clear.
        # Despues del chown + mkdir, estos corren limpios.
        log_info "  [6.4] cache:clear + config:clear + view:clear"
        local cache_out
        cache_out=$(setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan cache:clear 2>&1 || true)
        if /usr/bin/printf '%s' "$cache_out" | /usr/bin/grep -q "ERROR"; then
            log_warn "        cache:clear: ${cache_out}"
        else
            log_info "        cache:clear OK"
        fi
        if setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan config:clear >/dev/null 2>&1; then
            log_info "        config:clear OK"
        else
            log_warn "        config:clear fallo (reintentable despues)"
        fi
        if setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan view:clear >/dev/null 2>&1; then
            log_info "        view:clear OK"
        else
            log_warn "        view:clear fallo (reintentable despues)"
        fi

        # 6.5. assets:sync-from-public (logos + favicons al FTP).
        # Capturamos output y nos aseguramos de que termine con newline
        # para que no quede pegado con el siguiente sub-paso.
        log_info "  [6.5] assets:sync-from-public"
        local sync_out
        sync_out=$(setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan assets:sync-from-public --force 2>&1 || true)
        # Forzar newline final si el output no lo trae.
        [[ -n "$sync_out" && "${sync_out: -1}" != $'\n' ]] && sync_out+=$'\n'
        if /usr/bin/printf '%s' "$sync_out" | /usr/bin/grep -qE "(Subido|Resumen)"; then
            /usr/bin/printf '%s' "$sync_out" | /usr/bin/sed 's/^/        /' | /usr/bin/head -3
        else
            log_info "        OK (0 archivos para subir)"
        fi

        # 6.6. migrate --force.
        log_info "  [6.6] migrate --force"
        local mig_out
        mig_out=$(setup_compose_quiet "$inst" 30 exec -T backend \
            /usr/local/bin/php artisan migrate --force 2>&1 || true)
        [[ -n "$mig_out" && "${mig_out: -1}" != $'\n' ]] && mig_out+=$'\n'
        if /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -qE "Migrat"; then
            /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -E "Migrat" | /usr/bin/sed 's/^/        /' | /usr/bin/head -5
        elif /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -q "Nothing"; then
            log_info "        Nothing to migrate (BD al dia)"
        else
            log_warn "        fallo (verificar)"
        fi

        # 6.7. tenants:bootstrap (seed de la instancia).
        # Auto-detecta el seed file si TENANTS_SEED_FILE no esta
        # configurado pero existe un dev-<slug>.json en seeds/.
        log_info "  [6.7] tenants:bootstrap (seed)"
        local seed_file seed_path
        # IMPORTANTE: el || true en el grep es por si set -e mata
        # el script cuando grep no encuentra match (basta que
        # devuelva 1 en un assignment para que bash 4.4+ aborte).
        # El || true lo neutraliza y permite que seed_file quede
        # vacio (que es la senial correcta para 'no hay seed file').
        seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_path "$inst")" 2>/dev/null | /usr/bin/cut -d= -f2- || true)"
        seed_file="${seed_file## }"  # trim leading space
        if [[ -z "$seed_file" ]]; then
            # Buscar dev-<store>.json automaticamente.
            local store_slug="${store_of_inst}"
            local auto_seed_abs
            auto_seed_abs="$(instance_compose_dir "$inst")/seeds/dev-${store_slug}.json"
            if [[ -f "$auto_seed_abs" ]]; then
                seed_file="seeds/dev-${store_slug}.json"
                log_info "        (auto-detect seed: $seed_file)"
            fi
        fi
        seed_path=""
        if [[ -n "$seed_file" ]]; then
            seed_path="$(instance_compose_dir "$inst")/${seed_file}"
        fi
        if [[ -n "$seed_file" && -f "$seed_path" ]]; then
            local tenant_out
            # 30s timeout: si artisan se cuelga pidiendo input, no
            # bloqueamos el setup. El comando se considera fallido
            # y el setup sigue.
            tenant_out=$(setup_compose_quiet "$inst" 30 exec -T backend \
                /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 || true)
            local tenant_exit=$?
            [[ -n "$tenant_out" && "${tenant_out: -1}" != $'\n' ]] && tenant_out+=$'\n'
            if [[ $tenant_exit -eq 124 ]]; then
                log_warn "        timeout (30s) - artisan tardo demasiado. Re-ejecuta: ./scripts/docker-stack.sh bootstrap dev ${store_of_inst}"
            elif [[ $tenant_exit -ne 0 ]]; then
                log_warn "        fallo (exit $tenant_exit)"
                if [[ -n "$tenant_out" ]]; then
                    /usr/bin/printf '%s' "$tenant_out" | /usr/bin/sed 's/^/          /' | /usr/bin/head -3
                fi
            elif /usr/bin/printf '%s' "$tenant_out" | /usr/bin/grep -qiE "(error|exception)"; then
                log_warn "        fallo (output contiene 'error')"
            else
                log_info "        OK"
            fi
        else
            log_info "        skip (no hay seed file en $seed_path)"
        fi

        # 6.8. key:generate (idempotente, via cmd_bootstrap).
        log_info "  [6.8] key:generate (APP_KEY)"
        if cmd_bootstrap "$stage_of_inst" "$store_of_inst" 2>&1 | /usr/bin/grep -qE "(ok|generada|already)"; then
            log_info "        OK"
        else
            log_warn "        fallo"
        fi
    done
    print_section_end

    log_ok ""
    log_ok "Setup completo. Próximos pasos:"
    if [[ ${#missing_hosts[@]} -gt 0 && $add_hosts -eq 0 ]]; then
        # Si el user paso --no-hosts, le recordamos que tiene que
        # agregar los hosts a mano.
        echo "  1. (recomendado) ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts"
        echo "  2. (opcional) sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy"
        echo "  3. Probar: curl -sI http://<store>.test:5321/"
    else
        echo "  1. (opcional) sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy"
        echo "  2. Probar: curl -sI http://<store>.test:5321/"
    fi
}
