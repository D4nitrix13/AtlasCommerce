#!/usr/bin/env bash
# scripts/docker/inspection/logs.sh
# cmd_logs [instancia] [svc]
# Tail de logs. Si no se pasa instancia, mergea todas las que
# estén Up con prefijos por instancia.
cmd_logs() {
    local arg="${1:-all}"; shift || true
    if [[ "${arg}" == "all" || -z "${arg}" ]]; then
        log_info "Logs de ${C_BOLD}todas las instancias${C_RESET} (Ctrl+C para salir)…"
        log_cmd "/usr/bin/docker compose logs -f --tail=50 (merged de todas las instancias)"
        # Compose logs no soporta múltiples -f, usamos docker compose logs
        # en cada uno y mergeamos con labels.
        for inst in "${ALL_INSTANCES[@]}"; do
            if docker_compose "$inst" ps --services 2>/dev/null | /usr/bin/grep -q .; then
                docker_compose "$inst" logs -f --tail=50 "$@" 2>&1 | /usr/bin/sed "s|^|[$inst] |" &
            fi
        done
        /usr/bin/wait
    else
        docker_compose "$arg" logs -f --tail=100 "$@"
    fi
}