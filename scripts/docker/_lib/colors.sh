#!/usr/bin/env bash
# scripts/docker/_lib/colors.sh
# Constantes ANSI para colorear la salida. TTY-aware: si no hay
# terminal (CI, logs redirigidos a archivo), no emitimos códigos.
#
# Convención: cada mensaje de log usa una familia de color distinta
# para que sea fácil de seguir en logs largos:
#   - info  → azul
#   - ok    → verde
#   - warn  → amarillo
#   - error → rojo (a stderr)
#   - cmd   → dim/gris (se distingue del output del comando)
#   - bold  → para enfatizar nombres de instancia/servicio
if [[ -t 1 ]]; then
    C_RESET=$'\033[0m'
    C_BOLD=$'\033[1m'
    C_RED=$'\033[31m'
    C_GREEN=$'\033[32m'
    C_YELLOW=$'\033[33m'
    C_BLUE=$'\033[34m'
    C_MAGENTA=$'\033[35m'
    C_CYAN=$'\033[36m'
    C_DIM=$'\033[2m'
else
    C_RESET=""; C_BOLD=""; C_RED=""; C_GREEN=""; C_YELLOW=""
    C_BLUE=""; C_MAGENTA=""; C_CYAN=""; C_DIM=""
fi
