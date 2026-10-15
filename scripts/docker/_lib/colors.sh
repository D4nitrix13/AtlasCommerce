#!/usr/bin/env bash
# scripts/docker/_lib/colors.sh
# Constantes ANSI para colorear la salida.
#
# Convención: cada mensaje de log usa una familia de color distinta
# para que sea fácil de seguir en logs largos:
#   - info  → azul
#   - ok    → verde
#   - warn  → amarillo
#   - error → rojo (a stderr)
#   - cmd   → dim/gris (se distingue del output del comando)
#   - bold  → para enfatizar nombres de instancia/servicio
#
# IMPORTANTE: emitimos SIEMPRE códigos ANSI. Razon: el bug
# recurrente era que `[[ -t 1 ]]` devuelve false en scripts
# invocados via 'bash -c' o con stdout pipe'd (incluso si el
# usuario esta en terminal interactiva). Esto causaba que las
# constantes quedaran vacias y el output se viera plano. Las
# terminales modernas (xterm, gnome-terminal, iTerm, vscode
# terminal) soportan ANSI; las que no, muestran caracteres
# raros pero el dev lo identifica rapido.
#
# Si necesitas NO-COLOR (por ejemplo para CI logs que se commitean
# como texto plano), exporta ATLASCOMMERCE_NO_COLOR=1 antes de
# invocar el script.
if [[ "${ATLASCOMMERCE_NO_COLOR:-0}" == "1" ]]; then
    C_RESET=""; C_BOLD=""; C_DIM=""; C_UL=""
    C_RED=""; C_GREEN=""; C_YELLOW=""
    C_BLUE=""; C_MAGENTA=""; C_CYAN=""
    C_BRIGHT_RED=""; C_BRIGHT_GREEN=""; C_BRIGHT_YELLOW=""
    C_BRIGHT_BLUE=""; C_BRIGHT_MAGENTA=""; C_BRIGHT_CYAN=""
    C_BRIGHT_WHITE=""
else
    # Reset y modificadores.
    C_RESET=$'\033[0m'
    C_BOLD=$'\033[1m'
    C_DIM=$'\033[2m'
    C_UL=$'\033[4m'         # underline
    # Paleta base (30-37) + bold (1;) = BRIGHT BOLD. Mas visibles
    # que solo bright (90-97) que se ven casi como blanco en
    # terminales con temas oscuros. La combinacion bold+color
    # resalta mejor que el color solo.
    C_RED=$'\033[1;31m'              # bold + red
    C_GREEN=$'\033[1;32m'            # bold + green
    C_YELLOW=$'\033[1;33m'           # bold + yellow
    C_BLUE=$'\033[1;34m'             # bold + blue
    C_MAGENTA=$'\033[1;35m'          # bold + magenta
    C_CYAN=$'\033[1;36m'             # bold + cyan
    # Variantes BRIGHT BOLD (1;90-97). Mismo concepto, con los bright
    # base (90-97) + bold encima.
    C_BRIGHT_RED=$'\033[1;91m'
    C_BRIGHT_GREEN=$'\033[1;92m'
    C_BRIGHT_YELLOW=$'\033[1;93m'
    C_BRIGHT_BLUE=$'\033[1;94m'
    C_BRIGHT_MAGENTA=$'\033[1;95m'
    C_BRIGHT_CYAN=$'\033[1;96m'
    C_BRIGHT_WHITE=$'\033[1;97m'
fi
