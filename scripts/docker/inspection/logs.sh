#!/usr/bin/env bash
# scripts/docker/inspection/logs.sh
# cmd_logs <stage> <store> <servicio>
# Tail de logs. El servicio es OBLIGATORIO (no hay `all` implícito
# para logs — mergearlos enmascara qué instancia los emite). Si el
# store es `all`, hace tail paralelo de las 3 instancias del stage,
# cada uno con su prefijo `[<inst>]` para distinguir.
cmd_logs() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    if [[ -z "$svc" ]]; then
        # Defense-in-depth: el dispatcher ya exige servicio, pero si
        # alguien llama este script directo sin dispatcher, fallamos
        # acá también.
        log_err "logs requiere <servicio>."
        log_err "Uso: cmd_logs <stage> <store> <servicio>"
        return 1
    fi

    # Si el usuario pidió `all`, hacemos tail paralelo de cada
    # instancia del stage con prefijo para distinguir.
    if [[ "$store" == "all" ]]; then
        log_info "Logs de ${C_BOLD}todas las instancias de ${stage}${C_RESET} / servicio ${C_BOLD}${svc}${C_RESET} (Ctrl+C para salir)…"
        log_cmd "/usr/bin/docker compose logs -f --tail=50 (merged de ${stage}, svc=${svc})"
        local inst
        while IFS=$'\t' read -r inst _; do
            if docker_compose "$inst" ps --services 2>/dev/null | /usr/bin/grep -q .; then
                docker_compose "$inst" logs -f --tail=50 "$svc" 2>&1 | /usr/bin/sed "s|^|[$inst] |" &
            fi
        done < <(resolve_targets "$stage" "$store" "$svc")
        /usr/bin/wait
    else
        # 1 instancia específica.
        while IFS=$'\t' read -r inst _; do
            docker_compose "$inst" logs -f --tail=100 "$svc"
        done < <(resolve_targets "$stage" "$store" "$svc")
    fi
}
