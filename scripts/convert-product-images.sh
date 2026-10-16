#!/usr/bin/env bash
#
# convert-product-images.sh — convierte JPGs fuente a WebP responsive
# con naming algorithm consistente.
#
# USO:
#   ./scripts/convert-product-images.sh <source-dir> <output-base-dir> <product-slug>
#
# EJEMPLO:
#   ./scripts/convert-product-images.sh \
#     frontend/public/images/products/fender-player-fusion-bass-hh/source \
#     frontend/public/images/products/fender-player-fusion-bass-hh \
#     fender-player-fusion-bass-hh
#
# CONVENCIÓN DE INPUT:
#   Los archivos fuente deben seguir "{product-slug}-{variant-slug}-{NN}.jpg".
#   Ej: "fender-player-fusion-bass-hh-ebony-black-01.jpg"
#       "fender-player-fusion-bass-hh-halo-metallic-05.jpg"
#   - {product-slug} = slug del producto (mismas para el batch)
#   - {variant-slug} = slug de la variante ("ebony-black", "halo-metallic")
#   - {NN}           = índice zero-padded 01-N (orden en galería)
#
# ALGORITMO DE NOMBRE OUTPUT:
#   {product-slug}-{variant-slug}-{WxH}-{NN}.webp
#   E.g.: fender-player-fusion-bass-hh-ebony-black-1600x730-01.webp
#
# ESTRUCTURA OUTPUT:
#   <output-base-dir>/<variant-slug>/<archivos webp>
#   E.g.: public/images/products/fender-player-fusion-bass-hh/ebony-black/...
#
# TAMAÑOS:
#   1600, 1200, 800, 400 (alto proporcional al aspect ratio original 1600x730)
#   Calidad WebP q=85 — visualmente idéntico al JPG para fotos de producto,
#   ~30-50% más liviano.

set -euo pipefail

SRC="${1:?source dir requerido}"
OUT_BASE="${2:?output base dir requerido}"
SLUG="${3:?product slug requerido}"

SIZES=("1600x730" "1200x547" "800x365" "400x183")
QUALITY=85

if [ ! -d "$SRC" ]; then
  echo "Error: source dir no existe: $SRC" >&2
  exit 1
fi

shopt -s nullglob

# Recolectar JPGs y agrupar por variante.
# Patrón esperado: "{slug}-{variant}-{NN}.jpg"
# Después de remover el slug prefix, queda "{variant}-{NN}.jpg".
declare -A BY_VARIANT
for jpg in "$SRC"/*.jpg "$SRC"/*.jpeg "$SRC"/*.JPG "$SRC"/*.JPEG; do
  [ -f "$jpg" ] || continue
  base=$(basename "$jpg" | sed -E 's/\.(jpg|jpeg|JPG|JPEG)$//')

  # Validar prefijo: el archivo debe empezar con el slug
  if [ "${base#${SLUG}-}" = "$base" ]; then
    echo "  ⚠ Ignorando $jpg (no empieza con slug '${SLUG}-')" >&2
    continue
  fi

  remaining="${base#${SLUG}-}"   # ej: "ebony-black-01"
  # Extraer variant (todo menos el último -NN) y NN (los últimos 2 dígitos)
  nn=$(echo "$remaining" | grep -oE '[0-9]+$' || true)
  if [ -z "$nn" ]; then
    echo "  ⚠ Ignorando $jpg (no tiene índice numérico al final)" >&2
    continue
  fi
  variant_slug=$(echo "$remaining" | sed -E "s/-${nn}$//")

  BY_VARIANT[$variant_slug]+="$nn"$'\t'"$jpg"$'\n'
done

if [ ${#BY_VARIANT[@]} -eq 0 ]; then
  echo "Error: no JPGs válidos en $SRC (esperado: '${SLUG}-{variant}-{NN}.jpg')" >&2
  exit 1
fi

# Para cada variante, generar los 4 tamaños.
generated=0
for variant_slug in "${!BY_VARIANT[@]}"; do
  out_dir="$OUT_BASE/$variant_slug"
  mkdir -p "$out_dir"
  echo "→ Variante: $variant_slug → $out_dir"

  while IFS=$'\t' read -r nn src; do
    [ -z "$nn" ] && continue
    for size in "${SIZES[@]}"; do
      w=${size%x*}
      h=${size#*x}
      out="$out_dir/${SLUG}-${variant_slug}-${size}-${nn}.webp"
      cwebp -q "$QUALITY" -resize "$w" "$h" "$src" -o "$out" 2>/dev/null \
        && printf "    %s\n" "$(basename "$out")" \
        && generated=$((generated + 1))
    done
  done <<< "${BY_VARIANT[$variant_slug]}"
done

echo ""
echo "✓ Generados $generated archivos WebP en $OUT_BASE"
echo "  Tamaño JPG fuente total:  $(du -ch "$SRC"/*.{jpg,jpeg} 2>/dev/null | tail -1 | awk '{print $1}')"
echo "  Tamaño WebP output total: $(du -ch "$OUT_BASE"/*/*.webp 2>/dev/null | tail -1 | awk '{print $1}')"