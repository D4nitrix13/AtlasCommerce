#!/usr/bin/env bash
# scripts/docker/setup/first-install.sh
# cmd_setup <stage> <store|all>
# Primera instalación: copia docker/versions.env si falta, asegura
# .env de cada instancia, levanta los stacks. Acepta store=all para
# aplicar a todas las tiendas del stage.
cmd_setup() {
    local stage="$1"
    local store="${2:-all}"
    log_bold "Primera instalación de las instancias."
    # 1. docker/versions.env (centraliza versiones de imágenes)
    if [[ ! -f "${ROOT_DIR}/docker/versions.env" ]]; then
        if [[ -f "${ROOT_DIR}/docker/versions.env.example" ]]; then
            log_info "Creando docker/versions.env desde .example (editá versiones si querés pinear)."
            /usr/bin/cp "${ROOT_DIR}/docker/versions.env.example" "${ROOT_DIR}/docker/versions.env"
        else
            log_err "docker/versions.env.example no existe. Repo corrupto?"
            exit 1
        fi
    fi
    # 2. Resolver qué instancias levanta este setup según (stage, store).
    # El dispatcher ya normalizó stage (prod→production, dev→development).
    local -a instances_to_start=()
    while IFS=$'\t' read -r inst _; do
        instances_to_start+=("$inst")
    done < <(resolve_targets "$stage" "$store")
    log_info "Scope: stage=${stage} store=${store} (${#instances_to_start[@]} instancias: ${instances_to_start[*]})"
    # 3. Asegurar .env de cada instancia.
    for inst in "${instances_to_start[@]}"; do
        ensure_env_file "$inst"
    done
    # 4. Levantar.
    for inst in "${instances_to_start[@]}"; do
        log_info "Levantando ${C_BOLD}${inst}${C_RESET}…"
        docker_compose "$inst" up -d --remove-orphans
        log_ok "${inst} levantado."
    done
    log_ok "Setup completo. Próximos pasos:"
    echo "  1. sudo ./scripts/docker-stack.sh hosts >>/etc/hosts"
    echo "  2. sudo cp Caddyfile /etc/caddy/Caddyfile && sudo systemctl restart caddy"
    echo "  3. Probar: curl -sI http://panda-estampados.com/api/storefront/theme"
}