#!/usr/bin/env bash
#
# migrate-gibson-images.sh — Migra el dataset gibson-images/ al repo
# assets/products/instrumentos-musicales-la-voz/{brand}/...
#
# ADAPTADO DE scripts/convert-product-images.sh (que solo convertia
# JPGs planos por producto). Esta version:
#
#   - Recorre recursivamente todos los PNG del backup
#   - Infiere el role del filename {NN}-{role}.png
#   - Genera 4 sizes WebP (400, 800, 1200, 1600) preservando aspect ratio
#   - Output respeta naming convention del repo per-tenant:
#     {brand}/{product}/{variant}/{role}-{WxH}.webp
#   - Procesa en paralelo via xargs -P $(nproc)
#
# USO:
#   ./scripts/migrate-gibson-images.sh <source-cat> <source-brand>
#   ./scripts/migrate-gibson-images.sh --dry-run <source-cat> <source-brand>
#
# EJEMPLOS:
#   ./scripts/migrate-gibson-images.sh guitars gibson
#   ./scripts/migrate-gibson-images.sh basses epiphone --dry-run
#   ./scripts/migrate-gibson-images.sh amps mesa-boogie
#
# SOURCE esperado:
#   ~/.local/atlascommerce-archive/gibson-images/{cat}/{brand}/{product}/{finish}/{NN}-{role}.png
#
# OUTPUT generado:
#   assets/products/instrumentos-musicales-la-voz/{brand}/{product}/{finish}/{role}-{WxH}.webp
#
# REQUIREMENTS: cwebp (libwebp), xargs, nproc (coreutils).

set -eu
# (pipefail intentionally disabled: `find ... | while read` runs in subshell,
#  pipefail would surface unrelated signal noise. set -e catches real errors.)

# --- Arg parsing ---
DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=true
    shift
fi

CAT="${1:?categoria requerida (guitars/basses/amps/pedals/strings/capos/cases/apparel/parts)}"
BRAND="${2:?brand-slug requerido (gibson, gibson-custom, epiphone, kramer, mesa-boogie, maestro, etc)}"

# Validate args
if [[ ! "$CAT" =~ ^(guitars|basses|amps|pedals|strings|capos|cases|apparel|parts)$ ]]; then
    echo "Error: categoria invalida: $CAT" >&2
    echo "       validas: guitars basses amps pedals strings capos cases apparel parts" >&2
    exit 1
fi

# Paths (env-overridable for testing; defaults to canonical AtlasCommerce layout)
SOURCE_BASE="${SOURCE_BASE:-/home/d4nitrix13/.local/atlascommerce-archive/gibson-images}"
OUTPUT_BASE="${OUTPUT_BASE:-assets/products/instrumentos-musicales-la-voz}"

SRC="$SOURCE_BASE/$CAT/$BRAND"
OUT="$OUTPUT_BASE/$BRAND"

if [[ ! -d "$SRC" ]]; then
    echo "Error: source no existe: $SRC" >&2
    echo "       SOURCE_BASE=$SOURCE_BASE" >&2
    echo "       CAT=$CAT BRAND=$BRAND" >&2
    exit 1
fi

# Sizes (cuadrados para guitars/basses; product images del repo actual usan
# dimensiones cuadradas {WxH}. Para pedals/amps con aspect rectangular,
# preservamos aspect ratio via -resize W 0.)
SIZES=("400" "800" "1200" "1600")
QUALITY=85

# Detect cores for parallel cwebp
PARALLEL="$(nproc 2>/dev/null || echo 1)"

echo "=== migrate-gibson-images ==="
echo "Source:   $SRC"
echo "Output:   $OUT"
echo "Category: $CAT  Brand: $BRAND"
echo "Sizes:    ${SIZES[*]}"
echo "Quality:  $QUALITY"
echo "Parallel: $PARALLEL cores"
echo "Mode:     $(if $DRY_RUN; then echo DRY-RUN; else echo WRITE; fi)"
echo ""

# Count variants (informational only).
# Un "variant" = carpeta finish/{variant}/ dentro de {brand}/{product}/.
# Cada variant tiene N PNGs (1..15 dependiendo del producto).
VARIANT_COUNT=$(find "$SRC" -mindepth 2 -maxdepth 2 -type d | wc -l)
PRODUCT_COUNT=$(find "$SRC" -mindepth 1 -maxdepth 1 -type d | wc -l)
PNG_COUNT=$(find "$SRC" -type f -name "*.png" | wc -l)
echo "Productos en source: $PRODUCT_COUNT"
echo "Variants en source:  $VARIANT_COUNT"
echo "PNGs a convertir:     $PNG_COUNT"
echo "WebPs a generar:      $((PNG_COUNT * ${#SIZES[@]}))"
echo ""

if [[ "$DRY_RUN" == true ]]; then
    echo "DRY-RUN: no se escribira nada en el repo."
    echo ""
fi

# Build list of jobs: one per (PNG, size) pair.
# Format: 3 null-separated fields per job: <input_png> \0 <output_webp> \0 <size_w>
# Use a tmp file to avoid ARG_MAX for large datasets.
JOB_FILE="$(mktemp -t migrate-gibson-images.XXXXXX.jobs)"
trap 'rm -f "$JOB_FILE"' EXIT

# Roles conocidos (orden importa: longest-first para evitar matches parciales).
# Ordenado por longitud descendente para que las versiones con guion
# (e.g. "back-headstock") se matcheen antes que sus prefijos ("back", "headstock").
KNOWN_ROLES=(
    "back-headstock"
    "finish-back"
    "finish-peghead"
    "finish-sides"
    "finish-top"
    "sn-label"
    "tailpiece"
    "headstock"
    "backbody"
    "fingerboard"
    "binding"
    "pickups"
    "pickup"
    "front"
    "back"
    "body"
    "knobs"
    "side"
    "case"
    "beauty"
)

# Function: parse "NN-role" -> "NN|role"
# Retorna exit 0 incluso si el role es desconocido (lo marca con prefijo "unknown-").
# Esto hace el script robusto a roles nuevos sin romper el batch entero.
parse_role() {
    local base="$1"
    for r in "${KNOWN_ROLES[@]}"; do
        if [[ "$base" == *-"$r" ]]; then
            echo "${base%-$r}|$r"
            return 0
        fi
    done
    # Fallback: rol desconocido pero parseable por primer guion.
    # Emitimos warning a stderr para visibilidad y seguimos con el role crudo.
    local nn="${base%%-*}"
    local role="${base#*-}"
    echo "WARNING: rol desconocido '${role}' (NN=${nn})" >&2
    echo "${nn}|${role}"
    return 0
}

echo "Building job list..."

# Build job list using process substitution (no subshell, no pipefail issue).
# Format: 3 null-separated fields per job (png, out_webp, size).
while IFS= read -r -d '' png; do
    filename="$(basename "$png")"           # 01-body.png, 01-back-headstock.png
    base="${filename%.png}"                 # 01-body, 01-back-headstock
    variant="$(basename "$(dirname "$png")")"     # heritage-cherry
    product="$(basename "$(dirname "$(dirname "$png")")")"  # sg-standard
    # brand is the constant $BRAND from argv

    parsed="$(parse_role "$base")"           # "01|body"
    nn="${parsed%%|*}"                       # 01
    role="${parsed##*|}"                     # body, back-headstock, etc

    out_dir="$OUT/$product/$variant"

    for size in "${SIZES[@]}"; do
        out_webp="$out_dir/${role}-${size}x${size}.webp"
        if [[ "$DRY_RUN" == true ]]; then
            echo "DRY:  ${out_webp#$OUT/}  (NN=$nn role=$role)"
        else
            printf '%s\0%s\0%s\0' "$png" "$out_webp" "$size" >> "$JOB_FILE"
        fi
    done
done < <(find "$SRC" -type f -name "*.png" -print0)

if [[ "$DRY_RUN" == true ]]; then
    rm -f "$JOB_FILE"
    exit 0
fi

# Validate job list size before processing
JOB_SIZE=$(wc -c < "$JOB_FILE" 2>/dev/null || echo 0)
if [[ "$JOB_SIZE" == 0 ]]; then
    echo "Error: job file vacio, nada que procesar" >&2
    exit 1
fi
echo "Job file: $JOB_FILE ($JOB_SIZE bytes)"

# Process jobs in parallel.
# xargs -0 reads null-delimited lines from stdin, each "line" has 3 fields
# separated by null. -P $PARALLEL spawns $PARALLEL cwebp workers.
echo ""
echo "Processing $((PNG_COUNT * ${#SIZES[@]})) jobs with $PARALLEL parallel workers..."
echo ""

# Each xargs invocation passes 3 args to bash -c; preserve $QUALITY via env export.
# CRITICAL: el `_` despues de `bash -c` es OBLIGATORIO. Sin el placeholder,
# bash asigna el primer argumento de stdin a $0 (script name) en vez de $1,
# corriendo todos los args un lugar. Por eso el cwebp recibia args vacios.
export QUALITY OUT
xargs -0 -a "$JOB_FILE" -n 3 -P "$PARALLEL" bash -c '
    set -e
    png="$1"
    out_webp="$2"
    size="$3"
    mkdir -p "$(dirname "$out_webp")"
    # -resize W 0 preserva aspect ratio (W=target width, 0=auto height).
    # Para imagenes cuadradas (guitars/basses), equivalente a resize exacto.
    # Para rectangulares, preserve aspect evita distortion.
    if cwebp -q "$QUALITY" -resize "$size" 0 "$png" -o "$out_webp" 2>/dev/null; then
        printf "  ✓ %s\n" "${out_webp#"$OUT"/}"
    else
        printf "  ✗ %s (cwebp failed)\n" "${out_webp#"$OUT"/}" >&2
        exit 1
    fi
' _

JOB_RESULT=$?
rm -f "$JOB_FILE"

echo ""
echo "=== Done ==="
echo "WebPs generados en $OUT: $(find "$OUT" -type f -name "*.webp" | wc -l)"

exit $JOB_RESULT
