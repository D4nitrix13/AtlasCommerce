#!/usr/bin/env bash
# scripts/docker/_lib/logging.sh
# Funciones de log unificadas. Usan las constantes C_* definidas en
# colors.sh. Cada función toma printf-style args.
log_info()  { printf "${C_BLUE}[info]${C_RESET}  %s\n" "$*"; }
log_ok()    { printf "${C_GREEN}[ok]${C_RESET}    %s\n" "$*"; }
log_warn()  { printf "${C_YELLOW}[warn]${C_RESET}  %s\n" "$*"; }
log_err()   { printf "${C_RED}[error]${C_RESET} %s\n" "$*" >&2; }
log_bold()  { printf "${C_BOLD}%s${C_RESET}\n" "$*"; }

# log_cmd <comando>
# Imprime un comando con prefijo "[cmd] " antes de ejecutarlo, para
# que en logs en vivo se vea qué operación está corriendo el script
# por debajo. El comando se imprime tal cual lo escribimos en el
# caller — usar SIEMPRE rutas absolutas (/usr/bin/docker, /usr/bin/id,
# etc.) para evitar path hijacking.
#
# Display-only: si el comando contiene rutas bajo $HOME, se reemplazan
# por `~/...` para que el log sea legible. La ejecución usa SIEMPRE
# la ruta absoluta del caller (el script no toca el comando), asi que
# esto es solo cosmético.
#
# Variante single-line. Usada por inspection/* y volumes/* cuando
# ejecutan `docker` directo (no `docker_compose`). Para compose,
# preferí `log_cmd_block` que es multi-línea y coloreado por sección.
log_cmd() {
    local msg="$*"
    # Acortar "$HOME/" → "~/" en cualquier ocurrencia. Si $HOME no esta
    # seteado o es la raiz (/), no tocar el mensaje. La ejecución del
    # caller usa la ruta ABSOLUTA original — esto es solo cosmético.
    if [[ -n "$HOME" && "$HOME" != "/" ]]; then
        msg="${msg//$HOME\//~\/}"
    fi
    printf "${C_DIM}[cmd]${C_RESET}  %s\n" "$msg"
}

# log_cmd_block <cd_path> <env_string> <docker_token...>
# Imprime el comando `docker compose` con formato multi-línea y color
# por sección, pensado para que comandos largos (build --no-cache con
# muchos flags) se lean de un vistazo en vez de una línea interminable.
#
# Layout:
#   [cmd]
#         cd    <ruta absoluta de la instancia>
#         env   HOST_UID=1000 HOST_GID=1000
#         exec  /usr/bin/docker compose \
#                   -f docker-compose.yml \
#                   --env-file /path/.env \
#                   build --no-cache backend
#
# Coloreado:
#   - `cd`  → magenta bold (sección)
#   - path  → bright cyan
#   - `env` → yellow bold (sección) + white (vars)
#   - `exec` → green bold (sección)
#   - flags (-f, --env-file) → bright cyan, indentados con `\`
#   - positional args       → bright white
#
# No ejecuta nada: solo loguea. El caller debe correr el comando aparte.
log_cmd_block() {
    local cd_path="$1"
    local env_string="$2"
    shift 2

    # Display-only: acortar $HOME/ → ~/ en cd_path y en los tokens.
    # Si $HOME no esta seteado o es /, no tocar nada.
    if [[ -n "$HOME" && "$HOME" != "/" ]]; then
        cd_path="${cd_path//$HOME\//~\/}"
        local t new_args=()
        for t in "$@"; do
            t="${t//$HOME\//~\/}"
            new_args+=("$t")
        done
        set -- "${new_args[@]}"
    fi

    # Newline literal via ANSI-C quoting — bash en double-quotes NO
    # expande \n (lo deja como 2 chars literales), por eso usamos $'\n'.
    local NL=$'\n'

    printf "${C_DIM}[cmd]${C_RESET}${NL}"
    printf "      ${C_BOLD}${C_BRIGHT_MAGENTA}cd${C_RESET}   ${C_BRIGHT_CYAN}%s${C_RESET}${NL}" "$cd_path"
    if [[ -n "$env_string" ]]; then
        printf "      ${C_BOLD}${C_BRIGHT_YELLOW}env${C_RESET}  ${C_BRIGHT_WHITE}%s${C_RESET}${NL}" "$env_string"
    fi

    # Imprimir binario + args, con wrap automático cuando aparece un
    # flag (-foo / --foo) para que cada flag quede en su línea.
    printf "      ${C_BOLD}${C_BRIGHT_GREEN}exec${C_RESET} "
    local first=1
    local token
    for token in "$@"; do
        if [[ $first -eq 1 ]]; then
            printf "${C_BRIGHT_WHITE}%s${C_RESET}" "$token"
            first=0
            continue
        fi
        case "$token" in
            -*)
                # ` \\\n` literal: backslash + newline real (vía $NL).
                printf " \\${NL}              ${C_BOLD}${C_BRIGHT_CYAN}%s${C_RESET}" "$token"
                ;;
            *)
                printf " ${C_BRIGHT_WHITE}%s${C_RESET}" "$token"
                ;;
        esac
    done
    printf "${NL}"
}
