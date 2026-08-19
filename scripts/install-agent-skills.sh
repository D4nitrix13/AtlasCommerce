#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/install-agent-skills.sh
# ------------------------------------------------------------
# Crea symlinks en `.claude/skills/` que apuntan a
# `docs/agent-skills/` para que las skills commiteadas sean
# visibles al runtime de OpenCode.
#
# Idempotent: safe to re-run. Cada dev corre esto después de
# `git clone` para activar las project skills.
#
# Uso:
#   ./scripts/install-agent-skills.sh
#   ./scripts/install-agent-skills.sh --clean   # remove symlinks
# ------------------------------------------------------------
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$REPO_ROOT/docs/agent-skills"
TARGET_DIR="$REPO_ROOT/.claude/skills"

CLEAN=false
if [[ "${1:-}" == "--clean" ]]; then
  CLEAN=true
fi

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Error: $SOURCE_DIR no existe. ¿Estás en el repo correcto?"
  exit 1
fi

mkdir -p "$TARGET_DIR"

if [[ "$CLEAN" == true ]]; then
  echo "Removing project-skill symlinks from $TARGET_DIR ..."
  for d in "$SOURCE_DIR"/*; do
    [[ -d "$d" ]] || continue
    name=$(basename "$d")
    link="$TARGET_DIR/$name"
    if [[ -L "$link" ]]; then
      target=$(readlink "$link")
      if [[ "$target" == "../../docs/agent-skills/$name" ]]; then
        rm "$link"
        echo "  Removed: $name"
      fi
    fi
  done
  echo "Done."
  exit 0
fi

# Symlink each skill
count=0
for d in "$SOURCE_DIR"/*; do
  [[ -d "$d" ]] || continue
  name=$(basename "$d")
  link="$TARGET_DIR/$name"

  if [[ -L "$link" ]]; then
    target=$(readlink "$link")
    if [[ "$target" == "../../docs/agent-skills/$name" ]]; then
      echo "  Already linked: $name"
      continue
    fi
    echo "  Replacing existing symlink: $name ($target)"
    rm "$link"
  elif [[ -e "$link" ]]; then
    echo "  Skipping (not a symlink): $name"
    continue
  fi

  ln -s "../../docs/agent-skills/$name" "$link"
  count=$((count + 1))
  echo "  Linked: $name"
done

echo ""
echo "✅ Listo. $count project skill(s) instaladas."
echo ""
echo "Siguiente paso (si querés):"
echo "  ./scripts/install-marketplace-skills.sh   # instala plugins wshobson"
echo "  ./scripts/install-git-hooks.sh            # activa el pre-commit hook"
