#!/usr/bin/env bash
# scripts/docker/volumes/list.sh
# cmd_volumes <stage> <store> [<servicio>]
#
# Lista volúmenes Docker agrupados por categoría (data / cache / log /
# other) con tamaño real y descripción amigable.
#
# Tamaños vía `docker system df -v`. docker volume inspect NO devuelve
# tamaño por default (devuelve string vacío), por eso usamos df -v
# que sí trackea el tamaño real de cada volumen.
#
# El prefijo project+store+stage del nombre se quita usando el sufijo
# lógico (postgres_data, storage, etc.). Si el volumen no matchea
# ningún sufijo conocido, se muestra el nombre completo.
#
# <stage> y <store> son REQUERIDOS — el dispatcher los valida antes
# de llegar acá. Si store es 'all', se listan todas las del stage.
#
# Uso:
#   volumes dev all
#   volumes production all
#   volumes dev panda-estampados-and-kitsune
#   volumes dev all backend
cmd_volumes() {
    local stage="$1"
    local store="$2"
    local svc="${3:-}"

    log_info "Listando volúmenes Docker de los targets solicitados…"

    # Snapshot de tamaños. docker system df -v imprime secciones con
    # headers ("Local Volumes space usage:", "Build Cache space usage:",
    # etc.) y dentro de cada sección columnas VOLUME NAME / LINKS / SIZE.
    # docker volume inspect devuelve Size="" por default, no sirve acá.
    local size_table
    size_table="$(/usr/bin/docker system df -v 2>/dev/null | /usr/bin/awk '
        /^Local Volumes space usage:/ {flag=1; next}
        /^Build Cache space usage:/   {flag=0}
        /^Images space usage:/        {flag=0}
        /^Containers space usage:/    {flag=0}
        flag && NF >= 3 && !/^VOLUME NAME/ {print $1, $(NF-1), $NF}
    ')"

    while IFS=$'\t' read -r inst resolved_svc; do
        local dir file vols
        dir="$(instance_compose_dir "$inst")"
        file="$(instance_compose_file "$inst")"
        log_cmd "/usr/bin/docker compose -f ${dir}/${file} config --volumes"
        vols="$(cd "$dir" && HOST_UID="$(/usr/bin/id -u)" HOST_GID="$(/usr/bin/id -g)" /usr/bin/docker compose -f "$file" config --volumes 2>/dev/null || true)"
        if [[ -z "$vols" ]]; then
            log_warn "  ${inst}: no se encontraron volúmenes declarados."
            continue
        fi

        # Tabla short_name -> (size, full_name).
        #   short_name = sufijo lógico (postgres_data, storage, ...) para
        #                agrupar por categoría.
        #   full_name  = nombre EXACTO del volumen Docker (lo que ve
        #                `docker volume ls`). Es lo que mostramos al
        #                usuario para que pueda copiarlo a un
        #                `docker volume rm` / `docker volume inspect`.
        declare -A entries=()
        declare -A full_names=()
        local count=0 total_bytes=0
        while IFS= read -r volname; do
            [[ -z "$volname" ]] && continue
            local size
            size="$(printf '%s\n' "$size_table" | /usr/bin/awk -v n="$volname" '$1==n {print $3; exit}')"
            [[ -z "$size" ]] && size="0B"
            local short
            short="$(_vol_short_name "$volname")"
            entries["$short"]="$size"
            full_names["$short"]="$volname"
            count=$((count + 1))
            total_bytes=$((total_bytes + $(_size_to_bytes "$size")))
        done <<<"$vols"

        # Header de instancia: nombre + total (count + tamaño).
        printf "\n${C_BOLD}${C_BRIGHT_BLUE}==[ ${C_BRIGHT_CYAN}%s${C_BRIGHT_BLUE} ]==${C_RESET}  ${C_DIM}%d vols · %s${C_RESET}\n" \
            "$inst" "$count" "$(_bytes_to_human "$total_bytes")"

        # Recorrer categorías en orden: data, cache, log, other.
        local cat
        for cat in data cache log other; do
            # Filtrar entries de esta categoría y ordenar por nombre
            # completo (que es lo que ve el usuario).
            local cat_pairs=()
            local cat_bytes=0
            local s
            while IFS= read -r s; do
                [[ -z "$s" ]] && continue
                if [[ "$(_vol_category "$s")" == "$cat" ]]; then
                    cat_pairs+=("${full_names[$s]}|${entries[$s]}")
                    cat_bytes=$((cat_bytes + $(_size_to_bytes "${entries[$s]}")))
                fi
            done < <(printf '%s\n' "${!entries[@]}" | /usr/bin/sort)
            [[ ${#cat_pairs[@]} -eq 0 ]] && continue

            _print_category_header "$cat" "${#cat_pairs[@]}" "$cat_bytes"

            # Ancho dinámico para la columna de nombre completo (mín 36
            # para que los nombres largos de dev entren holgados).
            local max=0
            local pair fname
            for pair in "${cat_pairs[@]}"; do
                fname="${pair%|*}"
                [[ ${#fname} -gt $max ]] && max=${#fname}
            done
            [[ $max -lt 36 ]] && max=36

            for pair in "${cat_pairs[@]}"; do
                fname="${pair%|*}"
                size="${pair#*|}"
                local short="$(_vol_short_name "$fname")"
                printf "    ${C_BRIGHT_MAGENTA}%-${max}s${C_RESET}  ${C_BRIGHT_WHITE}%8s${C_RESET}  ${C_DIM}%-14s  %s${C_RESET}\n" \
                    "$fname" "$size" "$short" "$(_vol_description "$short")"
            done
        done
        unset entries full_names
    done < <(resolve_targets "$stage" "$store" "$svc")
}

# _vol_category <suffix>
# Devuelve la categoría lógica del volumen. Define el orden de
# visualización, el color del header y la semántica de criticidad.
# Las categorías son: data (CRÍTICO), cache (rebuildable), log, other.
_vol_category() {
    case "$1" in
        postgres_data|db-data)                       echo "data" ;;
        storage|storage-app)                         echo "data" ;;
        bootstrap|bootstrap-app)                     echo "data" ;;
        ftp_data|ftp-data)                           echo "data" ;;
        node_modules|pnpm_store|vite_cache|astro_cache) echo "cache" ;;
        logs|logs-app)                               echo "log" ;;
        *)                                           echo "other" ;;
    esac
}

# _vol_description <suffix>
# Texto descriptivo para cada volumen conocido. Si no hay match
# contra ningún sufijo conocido, devuelve "(sin descripción)" como
# fallback honesto — preferible a inventar o repetir el nombre.
_vol_description() {
    case "$1" in
        postgres_data|db-data)   echo "PostgreSQL database" ;;
        storage|storage-app)     echo "Laravel storage (uploads, framework cache)" ;;
        bootstrap|bootstrap-app) echo "Laravel bootstrap cache" ;;
        ftp_data|ftp-data)       echo "FTP uploaded files" ;;
        node_modules)            echo "npm/pnpm node_modules" ;;
        pnpm_store)              echo "pnpm package store" ;;
        vite_cache)              echo "vite dev cache" ;;
        astro_cache)             echo "astro build cache" ;;
        logs|logs-app)           echo "PHP error/access logs" ;;
        *)                       echo "(sin descripción)" ;;
    esac
}

# _vol_short_name <full_name>
# Devuelve el sufijo lógico del volumen (postgres_data, storage, etc).
# Si el nombre completo no contiene ningún sufijo conocido, devuelve
# el nombre completo como fallback (mejor que truncarlo arbitrariamente).
_vol_short_name() {
    local full="$1"
    local best=""
    local s
    for s in postgres_data db-data storage storage-app bootstrap bootstrap-app \
             ftp_data ftp-data node_modules pnpm_store vite_cache astro_cache \
             logs logs-app; do
        if [[ "$full" == *"$s" && ${#s} -gt ${#best} ]]; then
            best="$s"
        fi
    done
    if [[ -n "$best" ]]; then
        echo "$best"
    else
        echo "$full"
    fi
}

# _print_category_header <cat> <count> <bytes>
# Header de categoría con color según criticidad:
#   data  → rojo    (CRÍTICO — borra datos persistentes)
#   cache → amarillo (se reconstruye en `up`, safe to nuke)
#   log   → azul
#   other → gris
_print_category_header() {
    local cat="$1" count="$2" bytes="$3"
    local label color note=""
    case "$cat" in
        data)
            label="Data"
            color="${C_BOLD}${C_BRIGHT_RED}"
            note="critical — borra datos persistentes"
            ;;
        cache)
            label="Caches"
            color="${C_BOLD}${C_BRIGHT_YELLOW}"
            note="se reconstruyen en \`up\`"
            ;;
        log)
            label="Logs"
            color="${C_BOLD}${C_BRIGHT_BLUE}"
            ;;
        other)
            label="Other"
            color="${C_BOLD}${C_DIM}"
            ;;
    esac
    local vol_word="vols"
    [[ $count -eq 1 ]] && vol_word="vol"
    if [[ -n "$note" ]]; then
        printf "  %s%-7s${C_RESET}  ${C_DIM}%d %s · %s · %s${C_RESET}\n" \
            "$color" "$label" "$count" "$vol_word" "$(_bytes_to_human "$bytes")" "$note"
    else
        printf "  %s%-7s${C_RESET}  ${C_DIM}%d %s · %s${C_RESET}\n" \
            "$color" "$label" "$count" "$vol_word" "$(_bytes_to_human "$bytes")"
    fi
}

# _size_to_bytes <size_str>
# Convierte "1.18GB" -> bytes (para sumar). Acepta B/kB/MB/GB.
# Devuelve "0" si el string está vacío o no matchea ninguna unidad.
_size_to_bytes() {
    local s="${1:-0}"
    local num unit
    num="$(printf '%s' "$s" | /usr/bin/sed -E 's/^([0-9.]+).*/\1/')"
    [[ -z "$num" ]] && num="0"
    unit="$(printf '%s' "$s" | /usr/bin/sed -E 's/^[0-9.]+(.*)$/\1/')"
    case "$unit" in
        B)   echo "${num%.*}" ;;
        kB)  /usr/bin/awk -v n="$num" 'BEGIN{printf "%d", n*1024}' ;;
        MB)  /usr/bin/awk -v n="$num" 'BEGIN{printf "%d", n*1024*1024}' ;;
        GB)  /usr/bin/awk -v n="$num" 'BEGIN{printf "%d", n*1024*1024*1024}' ;;
        *)   echo "0" ;;
    esac
}

# _bytes_to_human <bytes>
# Formato humano: 2 decimales para GB, 1 para MB, 0 para kB/B, "0B" si 0.
_bytes_to_human() {
    /usr/bin/awk -v b="${1:-0}" 'BEGIN{
        if (b >= 1024*1024*1024) printf "%.2fGB", b/1024/1024/1024
        else if (b >= 1024*1024) printf "%.1fMB", b/1024/1024
        else if (b >= 1024) printf "%.0fkB", b/1024
        else printf "%dB", b
    }'
}