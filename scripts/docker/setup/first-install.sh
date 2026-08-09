#!/usr/bin/env bash
# scripts/docker/setup/first-install.sh
# cmd_setup [prod|dev|all]
# Primera instalación: copia docker/versions.env si falta, asegura
# .env de cada instancia, levanta los stacks.
cmd_setup() {
    local scope="${1:-all}"
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
    # 2. Resolver qué instancias levanta este setup según el scope.
    local -a instances_to_start=()
    case "$scope" in
        prod)
            instances_to_start=("${PROD_INSTANCES[@]}")
            log_info "Scope: prod (${PROD_INSTANCES[*]})"
            ;;
        dev)
            instances_to_start=("${DEV_INSTANCES[@]}")
            log_info "Scope: dev (${DEV_INSTANCES[*]})"
            ;;
        all)
            instances_to_start=("${ALL_INSTANCES[@]}")
            log_info "Scope: all (prod + dev)"
            ;;
        *)
            log_err "Scope inválido: $scope. Usá: prod | dev | all"
            exit 1
            ;;
    esac
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