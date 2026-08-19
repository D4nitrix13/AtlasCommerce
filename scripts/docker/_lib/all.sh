#!/usr/bin/env bash
# scripts/docker/_lib/all.sh
# Entry point de la librería compartida. Sourceado UNA sola vez por
# el dispatcher (docker-stack.sh) antes de cargar las acciones.
# Orden de source importa: colors → logging → resolve → summary
# → docker_compose. Las dependencias entre módulos son:
#   - logging necesita las constantes C_* de colors
#   - summary necesita ALL_INSTANCES/PROD_INSTANCES/DEV_INSTANCES de resolve
#   - docker_compose necesita instance_compose_dir/file/env_file de resolve
#   - resolve necesita log_err de logging
LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && /usr/bin/pwd)"
# shellcheck source=./colors.sh
source "${LIB_DIR}/colors.sh"
# shellcheck source=./logging.sh
source "${LIB_DIR}/logging.sh"
# shellcheck source=./resolve.sh
source "${LIB_DIR}/resolve.sh"
# shellcheck source=./summary.sh
source "${LIB_DIR}/summary.sh"
# shellcheck source=./docker_compose.sh
source "${LIB_DIR}/docker_compose.sh"