#!/usr/bin/env bash
# scripts/docker/inspection/info.sh
# cmd_info <stage> <store> [<svc>]
# Tabla detallada con instancia, servicio, estado, puertos y comando
# del container. Útil para diagnóstico: a simple vista se ve qué
# container de qué instancia está corriendo qué binario y qué puertos
# tiene publicados al host.
cmd_info() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    # docker ps --filter label=foo solo soporta AND entre labels, no
    # OR entre instancias. Iteramos por fuera y emitimos un único
    # header al inicio (con `tail -n +2` saltamos el header de las
    # llamadas siguientes).
    local header_printed=0
    local format_row='{{.Label "com.docker.compose.project"}}\t{{.Label "com.docker.compose.service"}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}\t{{.Command}}'
    while IFS=$'\t' read -r inst resolved_svc; do
        local filter_args=(--filter "label=com.docker.compose.project=$inst")
        [[ -n "$resolved_svc" ]] && filter_args+=(--filter "label=com.docker.compose.service=$resolved_svc")
        log_cmd "/usr/bin/docker ps -a ${filter_args[*]}"
        if [[ $header_printed -eq 0 ]]; then
            /usr/bin/docker ps -a "${filter_args[@]}" --format "table $format_row"
            header_printed=1
        else
            /usr/bin/docker ps -a "${filter_args[@]}" --format "$format_row" | /usr/bin/tail -n +2
        fi
    done < <(resolve_targets "$stage" "$store" "$svc")
}
