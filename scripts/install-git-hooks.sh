#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/install-git-hooks.sh
# ------------------------------------------------------------
# Activa los git hooks del proyecto en esta máquina.
# Idempotent: safe to re-run.
#
# Implementa la convención del proyecto: los hooks viven en
# `.githooks/` (commiteados al repo, compartibles con el equipo) y
# se activan vía `git config core.hooksPath`. Cada dev corre esto
# UNA vez por máquina después de `git clone`.
#
# Lo que se activa:
#   - .githooks/pre-commit → corre scripts/docker/quality-check.sh
#     si hay archivos Docker staged.
#
# Uso:
#   ./scripts/install-git-hooks.sh           # install
#   ./scripts/install-git-hooks.sh --clean   # uninstall
#
# Requisitos: bash, git.
# ------------------------------------------------------------
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && /usr/bin/pwd)"
GITHOOKS_DIR="${REPO_ROOT}/.githooks"
HOOKS_REL_PATH=".githooks"   # path relativo que se setea en core.hooksPath

CLEAN=false
if [[ "${1:-}" == "--clean" ]]; then
    CLEAN=true
fi

if ! /usr/bin/git rev-parse --git-dir >/dev/null 2>&1; then
    /usr/bin/printf "Error: este directorio no es un repo git.\n" >&2
    exit 1
fi

if [[ "$CLEAN" == true ]]; then
    /usr/bin/printf "Uninstall: removiendo core.hooksPath...\n"
    /usr/bin/git config --unset core.hooksPath || true
    /usr/bin/printf "Listo. git vuelve a usar .git/hooks/ por default.\n"
    exit 0
fi

if [[ ! -d "${GITHOOKS_DIR}" ]]; then
    /usr/bin/printf "Error: ${GITHOOKS_DIR} no existe en el repo.\n" >&2
    /usr/bin/printf "¿Alguien borró los hooks? Avisale al equipo.\n" >&2
    exit 1
fi

# Hook debe ser ejecutable. El install normal del repo ya lo commitea
# con +x (ver git ls-files --stage .githooks/); esto es defensa en
# profundidad en caso de que se haya perdido el bit.
for hook in "${GITHOOKS_DIR}"/*; do
    [[ -f "$hook" ]] || continue
    /usr/bin/chmod +x "$hook"
done

current="$(/usr/bin/git config --get core.hooksPath 2>/dev/null || true)"

if [[ "$current" == "$HOOKS_REL_PATH" ]]; then
    /usr/bin/printf "OK: core.hooksPath ya está en '${HOOKS_REL_PATH}' — sin cambios.\n"
else
    /usr/bin/git config core.hooksPath "${HOOKS_REL_PATH}"
    /usr/bin/printf "OK: core.hooksPath seteado a '${HOOKS_REL_PATH}'.\n"
    if [[ -n "$current" ]]; then
        /usr/bin/printf "    (antes estaba en: '%s')\n" "$current"
    fi
fi

echo
/usr/bin/printf "Hooks disponibles bajo ${GITHOOKS_DIR}/:\n"
/usr/bin/ls -l "${GITHOOKS_DIR}/" | /usr/bin/awk 'NR>1 {printf "  %s\n", $NF}'

echo
/usr/bin/printf "Para remover: ./scripts/install-git-hooks.sh --clean\n"
exit 0
