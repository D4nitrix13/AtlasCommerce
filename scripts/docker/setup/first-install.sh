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

cmd_setup() {
    local stage="$1"
    local store="${2:-all}"
    # Flag --no-hosts: si esta presente, NO intenta agregar hosts a
    # /etc/hosts (omite la fase 3). Por defecto los agrega automaticamente
    # cuando sudo -n funciona, o muestra el comando manual cuando no.
    local add_hosts=1
    for arg in "$@"; do
        case "$arg" in
            --no-hosts) add_hosts=0 ;;
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
    # 5. Backend bootstrap.
    print_section_title "Bootstrap" 6 "$TOTAL_PHASES" "Backend Laravel (storage:link + caches + migrate + tenants:bootstrap)"
    for inst in "${instances_to_start[@]}"; do
        log_info "Backend bootstrap de ${inst}"
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

        log_info "storage:link"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan storage:link 2>&1 | /usr/bin/grep -v "^\[No exception" || true

        # CRITICAL: el volumen storage/ se crea como root en el build,
        # pero PHP-FPM corre como UID 1000 (host user). Sin el chown,
        # view:clear falla y el primer 404/500 rompe porque PHP no puede
        # escribir los views compilados en storage/framework/views/.
        log_info "chown storage 1000:1000 (PHP-FPM user)"
        docker_compose "$inst" exec -u root -T backend \
            /bin/chown -R 1000:1000 /var/www/html/storage 2>&1 || true

        log_info "cache:clear + config:clear + view:clear"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan cache:clear 2>&1 | /usr/bin/grep -v "^$" || true
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan config:clear 2>&1 | /usr/bin/grep -v "^$" || true
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan view:clear 2>&1 | /usr/bin/grep -v "^$" || true

        log_info "assets:sync-from-public (logos + favicons al FTP)"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan assets:sync-from-public --force 2>&1 | /usr/bin/grep -E "(Subido|Resumen)" || true

        log_info "migrate --force"
        docker_compose "$inst" exec -T backend \
            /usr/local/bin/php artisan migrate --force 2>&1 | /usr/bin/grep -E "(Migrating|Migrated|ERROR|Nothing)" || true

        log_info "tenants:bootstrap (seed de la instancia)"
        local seed_file
        seed_file="$(/usr/bin/grep -E '^TENANTS_SEED_FILE=' "$(instance_env_path "$inst")" | /usr/bin/cut -d= -f2-)"
        if [[ -n "$seed_file" && -f "$(instance_compose_dir "$inst")/${seed_file#/}" ]]; then
            docker_compose "$inst" exec -T backend \
                /usr/local/bin/php artisan tenants:bootstrap --force 2>&1 | /usr/bin/grep -E "(Creando|tenant|ERROR)" || true
        else
            log_warn "Seed file no encontrado ($seed_file), skipping tenants:bootstrap"
        fi

        # key:generate (idempotente, via cmd_bootstrap).
        cmd_bootstrap "$stage_of_inst" "$store_of_inst"
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
