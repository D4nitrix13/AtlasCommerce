#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/install-marketplace-skills.sh
# ------------------------------------------------------------
# Instala los plugins del marketplace wshobson/agents que
# recomendamos para este proyecto. Idempotent: safe to re-run.
#
# Solo afecta el OpenCode config del usuario
# (~/.config/opencode/skills, agents, commands via symlinks).
# NO toca nada dentro del repo.
#
# Uso:
#   ./scripts/install-marketplace-skills.sh
#   ./scripts/install-marketplace-skills.sh --clean   # uninstall
#
# Requisitos: uv, gh (CLI), bash 4+, internet.
# ------------------------------------------------------------
set -euo pipefail

REPO="$HOME/agents-wshobson"
PLUGINS=(
  # Tier 1 — Core stack
  web-scripting
  javascript-typescript
  backend-development
  frontend-mobile-development
  database-design
  database-migrations
  unit-testing
  security-scanning
  # Tier 2 — Quality & workflow
  backend-api-security
  comprehensive-review
  tdd-workflows
  documentation-generation
  # Tier 3 — Conditional
  api-scaffolding
  accessibility-compliance
  observability-monitoring
  # Additional (genuinely useful)
  error-debugging
  code-refactoring
  developer-essentials
  api-testing-observability
)

CLEAN=false
if [[ "${1:-}" == "--clean" ]]; then
  CLEAN=true
fi

if ! command -v uv >/dev/null 2>&1; then
  echo "Error: uv no está instalado. Instalar desde https://docs.astral.sh/uv/"
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "Error: gh (GitHub CLI) no está instalado."
  exit 1
fi

if [[ "$CLEAN" == true ]]; then
  echo "Removing wshobson plugins from ~/.config/opencode/ ..."
  if [[ -d "$REPO" ]]; then
    ( cd "$REPO" && make uninstall-opencode ) || true
  fi
  echo "Done. Para reinstalar, correr el script sin --clean."
  exit 0
fi

# Clone/update the source repo
if [[ ! -d "$REPO" ]]; then
  echo "Clonando wshobson/agents a $REPO ..."
  gh repo clone wshobson/agents "$REPO" -- --depth 1
else
  echo "Actualizando wshobson/agents en $REPO ..."
  ( cd "$REPO" && git pull --ff-only )
fi

# Generate per-plugin artifacts (only the ones we want)
echo "Generando artifacts OpenCode para los ${#PLUGINS[@]} plugins ..."
for p in "${PLUGINS[@]}"; do
  ( cd "$REPO" && make generate HARNESS=opencode PLUGIN="$p" ) >/dev/null 2>&1
done

# Install symlinks into user's opencode config
echo "Instalando symlinks en ~/.config/opencode/ ..."
( cd "$REPO" && make install-opencode )

# Prune plugins we DIDN'T ask for (the install --all brings everything)
echo "Pruning plugins no seleccionados ..."
KEEP="$(IFS='|'; echo "${PLUGINS[*]}")"
for d in ~/.config/opencode/skills/*; do
  [[ -L "$d" ]] || continue
  name=$(basename "$d")
  prefix=$(echo "$name" | awk -F- '{print $1"-"$2}')
  if ! echo "$prefix" | grep -qE "^(${KEEP})$"; then
    rm "$d"
  fi
done
for f in ~/.config/opencode/agents/*; do
  [[ -L "$f" ]] || continue
  plugin=$(basename "$f" | sed 's/__.*//')
  if ! echo "$plugin" | grep -qE "^(${KEEP})$"; then
    rm "$f"
  fi
done
for f in ~/.config/opencode/commands/*; do
  [[ -L "$f" ]] || continue
  plugin=$(basename "$f" | sed 's/__.*//')
  if ! echo "$plugin" | grep -qE "^(${KEEP})$"; then
    rm "$f"
  fi
done

echo ""
echo "✅ Listo. Plugin counts:"
echo "   skills:   $(ls ~/.config/opencode/skills/ | wc -l)"
echo "   agents:   $(ls ~/.config/opencode/agents/ | wc -l)"
echo "   commands: $(ls ~/.config/opencode/commands/ | wc -l)"
echo ""
echo "Las project skills (laravel-multi-tenant, astro-themes, etc.) viven"
echo "en .claude/skills/ del repo y se commitean."
