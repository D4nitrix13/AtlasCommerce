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

# setup_compose_quiet <inst> <args...>
# Wrapper local de docker_compose() que suprime el bloque [cmd]
# repetitivo (cd/env/exec) que ensucia el output del setup. Solo
# imprime el resultado del comando (output + exit code). Los 8
# sub-pasos del bootstrap se vuelven legibles en vez de un wall
# de texto. Se usa SOLO en setup; el resto del orquestador sigue
# usando docker_compose() con el [cmd] block verbose.
setup_compose_quiet() {
    local name="$1"; shift
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
    (cd "$dir" && HOST_UID="${host_uid}" HOST_GID="${host_gid}" \
        ${DOCKER_BIN} compose -f "$file" $env_file "$@")
}

cmd_setup() {
    local stage="$1"
    local store="${2:-all}"
    # Flags aceptados:
    #   --no-hosts / -no-hosts  NO intenta agregar hosts a /etc/hosts
    #                            (omite la fase 3). Por defecto los agrega
    #                            automaticamente cuando sudo -n funciona, o
    #                            muestra el comando manual cuando no.
    # Aceptamos guion simple (-no-hosts) y doble (--no-hosts) para
    # flexibilidad con el usuario (convención del script: las flags
    # booleanas simples usan -flag en lugar de --flag).
    local add_hosts=1
    for arg in "$@"; do
        case "$arg" in
            --no-hosts|-no-hosts) add_hosts=0 ;;
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
            if ! /usr/bin/grep -qxE "[[:space:]]*[^#][[:space:]]+${host_entry}([[:space:]]|$)" /etc/hosts 2>/dev/null; then
                missing_hosts+=("$host_entry")
            fi
        done
    done
    if [[ ${#missing_hosts[@]} -gt 0 && $add_hosts -eq 1 ]]; then
        print_section_title "Hosts" 3 "$TOTAL_PHASES" "Faltan ${#missing_hosts[@]} entradas en /etc/hosts"
        for entry in "${missing_hosts[@]}"; do
            log_warn "  - ${entry}"
        done
        # Intentar agregar automaticamente con sudo -n (no-interactive).
        # Si funciona: silencioso. Si falla: mostrar comando manual.
        if /usr/bin/sudo -n true 2>/dev/null; then
            log_info "Agregando entradas a /etc/hosts con sudo (no-interactive)..."
            local block=""
            for entry in "${missing_hosts[@]}"; do
                block+="127.0.0.1  ${entry}"$'\n'
            done
            if /usr/bin/printf '%s' "$block" | /usr/bin/sudo /usr/bin/tee -a /etc/hosts >/dev/null; then
                log_ok "Hosts agregados a /etc/hosts."
            else
                log_warn "Fallo al escribir a /etc/hosts. Pegá este bloque manualmente (con sudo):"
                for entry in "${missing_hosts[@]}"; do
                    /usr/bin/printf '127.0.0.1  %s\n' "$entry"
                done
            fi
        else
            # Sin sudo no-interactive. Mostrar el comando exacto y un fallback
            # automatico via sudo (que pedira password interactivamente).
            log_warn "Para que el browser resuelva los hostnames, pegá este bloque al final de /etc/hosts (con sudo):"
            for entry in "${missing_hosts[@]}"; do
                /usr/bin/printf '127.0.0.1  %s\n' "$entry"
            done
            log_info "Comando equivalente: ./scripts/docker-stack.sh hosts | sudo tee -a /etc/hosts"
            log_info "O setup te lo agregara automaticamente si corres 'sudo -v' antes (cachea la sesion sudo)."
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

        # 6.2. chown storage 1000:1000 (PHP-FPM user).
        # CRITICAL: el volumen storage/ se crea como root en el build,
        # pero PHP-FPM corre como UID 1000. Si esto va DESPUES de
        # cache:clear, el clear falla con 'Failed to clear cache.
        # Make sure you have the appropriate permissions.' Por eso
        # va PRIMERO, antes de cualquier artisan.
        log_info "  [6.2] chown storage 1000:1000"
        if setup_compose_quiet "$inst" exec -u root -T backend \
            /bin/chown -R 1000:1000 /var/www/html/storage; then
            log_info "        OK"
        else
            log_warn "        fallo (no critico)"
        fi

        # 6.3. storage:link (idempotente; ignora error si ya existe).
        log_info "  [6.3] storage:link"
        local link_out
        link_out=$(setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan storage:link 2>&1 || true)
        if /usr/bin/printf '%s' "$link_out" | /usr/bin/grep -q "already exists"; then
            log_info "        (storage:link ya existia, skip)"
        else
            log_info "        OK"
        fi

        # 6.4. cache:clear + config:clear + view:clear.
        # Despues del chown, estos corren limpios.
        log_info "  [6.4] cache:clear + config:clear + view:clear"
        local cache_out
        cache_out=$(setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan cache:clear 2>&1 || true)
        if /usr/bin/printf '%s' "$cache_out" | /usr/bin/grep -q "ERROR"; then
            log_warn "        cache:clear: ${cache_out}"
        else
            log_info "        cache:clear OK"
        fi
        if setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan config:clear >/dev/null 2>&1; then
            log_info "        config:clear OK"
        else
            log_warn "        config:clear fallo (reintentable despues)"
        fi
        if setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan view:clear >/dev/null 2>&1; then
            log_info "        view:clear OK"
        else
            log_warn "        view:clear fallo (reintentable despues)"
        fi

        # 6.5. assets:sync-from-public (logos + favicons al FTP).
        log_info "  [6.5] assets:sync-from-public"
        local sync_out
        sync_out=$(setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan assets:sync-from-public --force 2>&1 || true)
        if /usr/bin/printf '%s' "$sync_out" | /usr/bin/grep -qE "(Subido|Resumen)"; then
            /usr/bin/printf '%s' "$sync_out" | /usr/bin/sed 's/^/        /' | /usr/bin/head -3
        else
            log_info "        OK (0 archivos para subir)"
        fi

        # 6.6. migrate --force.
        log_info "  [6.6] migrate --force"
        local mig_out
        mig_out=$(setup_compose_quiet "$inst" exec -T backend \
            /usr/local/bin/php artisan migrate --force 2>&1 || true)
        if /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -qE "Migrat"; then
            /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -E "Migrat" | /usr/bin/sed 's/^/        /' | /usr/bin/head -5
        elif /usr/bin/printf '%s' "$mig_out" | /usr/bin/grep -q "Nothing"; then
            log_info "        Nothing to migrate (BD al dia)"
        else
            log_warn "        fallo (verificar)"
        fi

        # 6.7. tenants:bootstrap (seed de la instancia).
        log_info "  [6.7] tenants:bootstrap (seed)"
        local seed_file
        seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_path "$inst")" | /usr/bin/cut -d= -f2-)"
        if [[ -n "$seed_file" && -f "$(instance_compose_dir "$inst")/${seed_file#/}" ]]; then
            local tenant_out
            tenant_out=$(setup_compose_quiet "$inst" exec -T backend \
                /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 || true)
            if /usr/bin/printf '%s' "$tenant_out" | /usr/bin/grep -qiE "(error|exception)"; then
                log_warn "        fallo (verificar)"
            else
                log_info "        OK"
            fi
        else
            log_info "        skip (seed file no configurado: $seed_file)"
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
