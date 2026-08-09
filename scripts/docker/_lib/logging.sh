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
log_cmd()   { printf "${C_DIM}[cmd]${C_RESET}  %s\n" "$*"; }
