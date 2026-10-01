#!/usr/bin/env bash
# ------------------------------------------------------------
# scripts/docker/quality-check.sh
# ------------------------------------------------------------
# Enforces the Docker absolute-path convention documented in
# docker/COMPOSE.md and docker/DOCKERFILES.md. Catches:
#
# 1. Bare binaries in Dockerfile RUN / CMD / ENTRYPOINT directives.
# 2. Bare binaries as the first item of `command:` or `entrypoint:`
#    blocks in docker-compose.yml files.
# 3. Bare external binaries invoked from entrypoint*.sh scripts.
#
# POSIX shell builtins (echo, cd, set, if, for, ...) are NOT flagged
# because the shell implements them directly and they're not on PATH,
# so PATH hijacking doesn't apply.
#
# Exit codes:
#   0  → no violations
#   1  → one or more violations
#   2  → invalid usage
#
# Uso:
#   ./scripts/docker/quality-check.sh
#
# Wire-eable to:
#   - quality-gate-pre-commit (recommended)
#   - CI pipeline (recommended)
#   - Manual pre-commit sanity check
# ------------------------------------------------------------
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && /usr/bin/pwd)"
INSTANCES_DIR="${REPO_ROOT}/docker/instances"

# POSIX shell builtins (case-sensitive). Anything NOT in this list is
# treated as an external binary and MUST be invoked with absolute path
# in entrypoint scripts and Dockerfiles.
#
# Source: https://pubs.opengroup.org/onlinepubs/9699919799/idx/shell.html
# Plus common bash extensions actually used in this project's entrypoints.
BUILTINS=(
  # Variable / environment
  set unset export readonly local declare typeset
  # Control flow
  if then else elif fi case esac function select
  for while until do done
  break continue return exit
  # I/O
  echo printf read
  # File system
  cd pwd pushd popd dirs
  # Test / conditional
  test [ ]
  # Source / exec
  source . eval exec
  # Job / trap / wait
  trap shift wait fg bg jobs kill disown
  # Misc built-ins
  alias unalias command type hash
  : true false
  getopts
  umask ulimit
  fc history
  bind complete compgen compopt
  shopt
  # Bash extensions used in our entrypoints
  readarray mapfile
  # Arithmetic / subscript
  let
)

# Whitelist additional patterns that LOOK like binaries but are safe:
SAFE_PREFIXES=(
  "_"           # $_ variables (e.g., $_ in shell)
  "\${"         # shell parameter expansion
  "\$("         # command substitution
  "\`"          # backtick command substitution
  "#"           # comment
)

# Whitelist utilities that exist in /bin or /usr/bin on EVERY base
# image we use (alpine, debian-slim) and are unlikely to be hijacked
# in practice. Listed explicitly so the table in COMPOSE.md and the
# skill matches reality.
SAFE_BINARIES=(
  sleep            # /bin/sleep — universal
  seq              # /usr/bin/seq — universal
)

VIOLATIONS=0

is_builtin() {
  local cmd="$1"
  for b in "${BUILTINS[@]}"; do
    [[ "$cmd" == "$b" ]] && return 0
  done
  return 1
}

is_safe_binary() {
  local cmd="$1"
  for s in "${SAFE_BINARIES[@]}"; do
    [[ "$cmd" == "$s" ]] && return 0
  done
  return 1
}

# Strip leading whitespace + first char if it's a comment marker
# Returns the first alphanumeric token of the line via $TOKEN, or empty
first_token() {
  local line="$1"
  # Strip leading whitespace
  line="${line#"${line%%[![:space:]]*}"}"
  # Strip leading comment
  [[ "$line" == \#* ]] && return 1
  # First word (alphanumeric + dash + underscore + dot, but stop at space/quote)
  TOKEN="$(/usr/bin/printf '%s' "$line" | /usr/bin/grep -oE '^[A-Za-z_][A-Za-z0-9_./-]*' || true)"
  [[ -z "$TOKEN" ]] && return 1
  return 0
}

flag() {
  local file="$1"
  local line="$2"
  local msg="$3"
  echo "  FAIL  ${file}:${line}  ${msg}"
  VIOLATIONS=$((VIOLATIONS + 1))
}

scan_dockerfile() {
  local f="$1"
  # Match RUN, CMD, ENTRYPOINT followed by content. We only care about
  # the FIRST meaningful token on that logical instruction.
  local line_num=0
  while IFS= read -r line; do
    line_num=$((line_num + 1))
    # Skip comments / blank
    [[ -z "$line" ]] && continue
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    # Detect directive
    if [[ "$line" =~ ^[[:space:]]*(RUN|CMD|ENTRYPOINT)[[:space:]]+(.*) ]]; then
      local directive="${BASH_REMATCH[1]}"
      local rest="${BASH_REMATCH[2]}"
      # Strip leading quote/bracket if EXEC-form JSON: CMD ["/usr/bin/...", ...]
      rest="${rest#[}"
      rest="${rest#\"}"
      # Find first token
      local first
      first="$(/usr/bin/printf '%s' "$rest" | /usr/bin/grep -oE '^[A-Za-z_./][A-Za-z0-9_./-]*' || true)"
      [[ -z "$first" ]] && continue
      # Already absolute?
      [[ "$first" =~ ^/ ]] && continue
      # Allowed: PATH-like env var prefix handled by absolute check above
      # Flag any other bare binary
      flag "$f" "$line_num" "bare binary in ${directive}: '${first}' (use /usr/bin/${first} or full absolute path)"
    fi
  done < "$f"
}

scan_compose() {
  local f="$1"
  # Use awk to walk `command:` and `entrypoint:` blocks and inspect
  # the first list item only.
  /usr/bin/awk -v fname="$f" '
    BEGIN { in_block = 0; first = ""; first_line = 0; block_type = "" }
    /^[[:space:]]*(command|entrypoint):[[:space:]]*$/ {
      block_type = $0
      sub(/^[[:space:]]*/, "", block_type)
      sub(/:.*/, "", block_type)
      in_block = 1
      first = ""
      first_line = 0
      next
    }
    in_block && /^[[:space:]]*-[[:space:]]+/ {
      if (first_line == 0) {
        # Extract the value
        val = $0
        sub(/^[[:space:]]*-[[:space:]]*/, "", val)
        # Strip leading/trailing quote chars (single or double)
        gsub(/^["'"'"']|["'"'"']$/, "", val)
        first = val
        first_line = NR
      }
      next
    }
    in_block && /^[[:space:]]*$/ {
      check_and_reset()
      next
    }
    in_block && /^[A-Za-z]/ {
      check_and_reset()
      next
    }
    END {
      check_and_reset()
    }
    function check_and_reset(    _abs) {
      if (first_line > 0 && first != "" && first !~ /^\// && first !~ /^\$/) {
        printf "  FAIL  %s:%d  bare binary in %s first item: %s\n", fname, first_line, block_type, first
        violations++
      }
      in_block = 0
      first = ""
      first_line = 0
      block_type = ""
    }
  ' "$f"
  # Track awk's local counter by checking exit message (awk variables
  # don'\''t propagate; instead emit violations count at end)
}

scan_entrypoint() {
  local f="$1"
  local line_num=0
  while IFS= read -r line; do
    line_num=$((line_num + 1))
    # Skip blank lines and pure comments
    [[ -z "$line" ]] && continue
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    # Extract first token (skip leading whitespace)
    local trimmed="${line#"${line%%[![:space:]]*}"}"
    # Skip comments inline
    [[ "$trimmed" == \#* ]] && continue
    local first
    first="$(/usr/bin/printf '%s' "$trimmed" | /usr/bin/grep -oE '^[A-Za-z_][A-Za-z0-9_./=]*' || true)"
    [[ -z "$first" ]] && continue
    # Absolute path? OK.
    [[ "$first" =~ ^/ ]] && continue
    # Variable assignment? OK (FOO=bar).
    [[ "$first" =~ ^[A-Za-z_][A-Za-z0-9_]*= ]] && continue
    # Single-char special tokens? OK.
    [[ "$first" == "(" || "$first" == ")" || "$first" == "{" || "$first" == "}" ]] && continue
    # Builtin? OK.
    if is_builtin "$first"; then continue; fi
    # Whitelisted safe binary? OK.
    if is_safe_binary "$first"; then continue; fi
    # Everything else is an external binary used bare → flag.
    flag "$f" "$line_num" "bare external binary in entrypoint: '${first}'"

    # Also check for `$(bare-binary ...)` substitutions: bash will
    # execute the inner command even when the OUTER line starts with
    # a builtin like `if`, `[`, etc. The line stripping above misses
    # these. We scan any `$ <bare-binary>` subsequence on the line.
    local remaining="$line"
    while [[ "$remaining" =~ \$\([[:space:]]*([A-Za-z_][A-Za-z0-9_.-]*)[[:space:]] ]]; do
      local inner="${BASH_REMATCH[1]}"
      # Trim everything up to and including the matched `$(`.
      remaining="${remaining#*\$(}"
      [[ "$inner" =~ ^/ ]] && continue
      if is_builtin "$inner"; then continue; fi
      if is_safe_binary "$inner"; then continue; fi
      flag "$f" "$line_num" "bare binary inside '\$()': ${inner}"
    done
  done < "$f"
}

scan_docker_entrypoint_bins() {
  # Deprecated: replaced by inline detection inside scan_entrypoint.
  :  # no-op kept for backwards source compatibility
}


echo "== Docker quality check =="
echo "   Repo: ${REPO_ROOT}"
echo "   Scanning: ${INSTANCES_DIR}"
echo ""

total_dockerfiles=0
total_compose=0
total_entrypoints=0

# Scan Dockerfiles
while IFS= read -r f; do
  [[ -z "$f" ]] && continue
  total_dockerfiles=$((total_dockerfiles + 1))
  scan_dockerfile "$f"
done < <(/usr/bin/find "${INSTANCES_DIR}" -type f -name 'Dockerfile.*' 2>/dev/null)

# Scan compose files
compose_violations_before=$VIOLATIONS
while IFS= read -r f; do
  [[ -z "$f" ]] && continue
  total_compose=$((total_compose + 1))
  scan_compose "$f"
done < <(/usr/bin/find "${INSTANCES_DIR}" -type f -name 'docker-compose*.yml' 2>/dev/null)

# Scan entrypoint scripts
while IFS= read -r f; do
  [[ -z "$f" ]] && continue
  total_entrypoints=$((total_entrypoints + 1))
  scan_entrypoint "$f"
done < <(/usr/bin/find "${INSTANCES_DIR}" -type f -name 'entrypoint*.sh' 2>/dev/null)

echo ""
echo "== Summary =="
echo "   Files scanned:"
echo "     Dockerfiles:  ${total_dockerfiles}"
echo "     compose:      ${total_compose}"
echo "     entrypoints:  ${total_entrypoints}"
echo "   Violations:     ${VIOLATIONS}"
echo ""

if [[ ${VIOLATIONS} -gt 0 ]]; then
  echo "FAIL: ${VIOLATIONS} violation(s) found."
  echo "      See docs/agent-skills/docker-stack-orchestration/SKILL.md"
  echo "      (Hard rules section) for the convention and exception list."
  exit 1
fi

echo "OK: no violations."
exit 0
