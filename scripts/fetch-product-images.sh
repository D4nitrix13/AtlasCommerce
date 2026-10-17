#!/usr/bin/env bash
#
# fetch-product-images.sh — descarga imágenes de producto desde el
# CDN oficial de Gibson/Fender y las convierte a WebP responsive
# preservando aspect ratio.
#
# Diferencia con scripts/convert-product-images.sh:
#   - convert-product-images.sh: convierte JPGs YA LOCALES a WebP
#     (hardcoded 1600x730 aspect para Fender bass HH)
#   - fetch-product-images.sh: descarga PNGs originales desde la URL
#     del fabricante y genera WebPs a 4 widths preservando el aspect
#     ratio natural del source (cuadrado para Gibson, landscape 3:1 para
#     Fender AmPro, etc.)
#
# USO:
#   ./scripts/fetch-product-images.sh <manifest.json>
#
# FORMATO DEL MANIFEST:
#   [
#     {
#       "slug": "gibson-mod-collection-es-335-smooth-sepia",
#       "variant_slug": "smooth-sepia",
#       "front_url": "https://www.gibson.com/cdn/shop/files/RAM..._3_Front.png?v=...",
#       "back_url": "https://www.gibson.com/cdn/shop/files/RAM..._4_Back.png?v=..."
#     },
#     ...
#   ]
#
# OUTPUT:
#   frontend/public/images/products/{slug}/{variant_slug}/
#     {slug}-{variant_slug}-front-1600x{H}-01.webp
#     {slug}-{variant_slug}-back-1600x{H}-02.webp
#     + 3 widths menores (1200/800/400) de cada uno
#
# EJEMPLO:
#   ./scripts/fetch-product-images.sh /tmp/gibson-batch.json
#
# NOTAS:
#   - El manifest lo genera un agente (o el humano) a partir de las
#     URLs scrapeadas del sitio del fabricante. El agente tiene que
#     escrapear Gibson vía /products.json (libre) o Fender vía
#     webfetch (browser fingerprint — el /products.json de Fender está
#     bloqueado por Cloudflare).
#   - Aspect ratio del source se preserva via `cwebp -resize W 0`.
#   - Requiere: curl, cwebp, identify (ImageMagick), jq.

set -euo pipefail

MANIFEST="${1:?manifest JSON requerido}"

if [ ! -f "$MANIFEST" ]; then
  echo "Error: manifest no existe: $MANIFEST" >&2
  exit 1
fi

DEST_BASE="frontend/public/images/products"
SIZES=(1600 1200 800 400)
QUALITY=85

# Lee manifest
PRODUCTS=$(jq -c '.[]' "$MANIFEST")
COUNT=$(echo "$PRODUCTS" | wc -l)
echo "Procesando $COUNT productos..."
echo ""

GENERATED=0
FAILED=0
echo "$PRODUCTS" | while read -r product; do
  SLUG=$(echo "$product" | jq -r '.slug')
  VARIANT=$(echo "$product" | jq -r '.variant_slug')
  FRONT_URL=$(echo "$product" | jq -r '.front_url // empty')
  BACK_URL=$(echo "$product" | jq -r '.back_url // empty')
  OUT_DIR="$DEST_BASE/$SLUG/$VARIANT"
  mkdir -p "$OUT_DIR"

  echo "→ $SLUG / $VARIANT"

  TMP_DIR=$(mktemp -d)
  for view in front back; do
    URL=$(echo "$product" | jq -r ".${view}_url // empty")
    if [ -z "$URL" ]; then continue; fi

    IN_PNG="$TMP_DIR/${view}.png"
    if ! curl -sSL -A "Mozilla/5.0" -o "$IN_PNG" "$URL"; then
      echo "  ✗ $view: download falló"
      continue
    fi

    # Valida que sea PNG real
    if ! file "$IN_PNG" | grep -q "PNG image"; then
      echo "  ✗ $view: no es PNG ($(file -b "$IN_PNG" | head -c 30)...)"
      rm "$IN_PNG"
      continue
    fi

    # Lee aspect natural del source
    SRC_W=$(identify -format "%w" "$IN_PNG")
    SRC_H=$(identify -format "%h" "$IN_PNG")
    if [ -z "$SRC_W" ] || [ -z "$SRC_H" ] || [ "$SRC_W" = "0" ]; then
      echo "  ✗ $view: no pude leer dimensiones"
      rm "$IN_PNG"
      continue
    fi

    # Genera 4 sizes preservando aspect
    INDEX="01"
    [ "$view" = "back" ] && INDEX="02"

    for w in "${SIZES[@]}"; do
      # Calcula height proporcional
      h=$(echo "scale=0; $w * $SRC_H / $SRC_W" | bc)
      OUT="$OUT_DIR/${SLUG}-${VARIANT}-${w}x${h}-${INDEX}.webp"
      if cwebp -q "$QUALITY" -resize "$w" 0 "$IN_PNG" -o "$OUT" 2>/dev/null; then
        :
      else
        echo "  ✗ $view @ ${w}w: convert falló"
      fi
    done
    rm "$IN_PNG"
  done

  rm -rf "$TMP_DIR"

  # Reporte por producto
  COUNT=$(ls "$OUT_DIR" 2>/dev/null | wc -l)
  echo "  ✓ $COUNT webps"
done

echo ""
echo "✓ Pipeline completo."
