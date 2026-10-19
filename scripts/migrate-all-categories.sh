#!/usr/bin/env bash
#
# migrate-all-categories.sh — Genera WebPs multi-size para TODAS
# las categorias del backup staging (~/.local/atlascommerce-archive/
# gibson-images/), respetando la estructura per-tenant del repo.
#
# Mapeo del backup (heterogeneo) al output per-tenant:
#   amps/heads/mesa-boogie/foo/01-front.png
#     -> assets/products/instrumentos-musicales-la-voz/mesa-boogie/foo/01-front-1600x1600.webp
#   pedals/epiphone/foo/01-front.png
#     -> assets/products/instrumentos-musicales-la-voz/epiphone/foo/01-front-1600x1600.webp
#   strings/acoustic/gibson/foo/01.png
#     -> assets/products/instrumentos-musicales-la-voz/gibson/foo/01-1600x1600.webp
#   capos/gibson/foo/01.png
#     -> assets/products/instrumentos-musicales-la-voz/gibson/foo/01-1600x1600.webp
#   cases/soft/gibson/foo/01.png
#     -> assets/products/instrumentos-musicales-la-voz/gibson/foo/01-1600x1600.webp
#   apparel/headwear/gibson/foo/01.png
#     -> assets/products/instrumentos-musicales-la-voz/gibson/foo/01-1600x1600.webp
#   parts/tailpieces/gibson/foo/01.png
#     -> assets/products/instrumentos-musicales-la-voz/gibson/foo/01-1600x1600.webp
#
# Reglas de extraccion de brand:
#   - amps: brand = TERCER nivel (amps/{heads|combos}/<brand>/...)
#   - TODO lo demas: brand = SEGUNDO nivel ({cat}/{brand}/...)
#
# ⚠️ IMPORTANTE: este script SOLO convierte PNG a WebP. NO hashea
# storage keys ni sube a FTP — eso es responsabilidad de
# `php artisan assets:seed-catalog` y/o `assets:sync-from-public`.
#
# Requiere: cwebp (libwebp), ImageMagick (identify, optional).
#
# Uso:
#   ./scripts/migrate-all-categories.sh                  # todas las categorias
#   ./scripts/migrate-all-categories.sh pedals strings   # subset

set -euo pipefail

BACKUP="${ATLAS_BACKUP_BASE:-/home/d4nitrix13/.local/atlascommerce-archive/gibson-images}"
OUT="${ATLAS_ASSET_OUT:-assets/products/instrumentos-musicales-la-voz}"

if [ ! -d "$BACKUP" ]; then
    echo "ERROR: backup no existe: $BACKUP" >&2
    exit 1
fi

# Categorias a procesar (si se pasan args, usar esos; si no, todas).
ALL_CATS=(amps pedals strings capos cases apparel parts)
if [ $# -gt 0 ]; then
    CATS=("$@")
else
    CATS=("${ALL_CATS[@]}")
fi

SIZES=(400 800 1200 1600)
QUALITY=85
TOTAL_GENERATED=0
TOTAL_SKIPPED=0

# Funcion helper: extraer brand segun la categoria
extract_brand() {
    local category="$1" rel="$2"
    case "$category" in
        amps)
            # Estructura: amps/{heads|combos}/<brand>/... (5 niveles)
            echo "$rel" | cut -d/ -f2
            ;;
        strings|cases|apparel|parts)
            # Estructura: {cat}/{subcat}/<brand>/<product>/... (4 niveles)
            echo "$rel" | cut -d/ -f2
            ;;
        *)
            # Estructura: {cat}/<brand>/... (3 niveles, sin subcat)
            echo "$rel" | cut -d/ -f1
            ;;
    esac
}

# Funcion helper: extraer subpath (lo que viene despues de brand)
extract_subpath() {
    local category="$1" rel="$2"
    case "$category" in
        amps)
            # amps/heads/mesa-boogie/mark-five-35/01.png -> heads/mark-five-35/01.png
            echo "$rel" | cut -d/ -f3-
            ;;
        strings|cases|apparel|parts)
            # {cat}/<subcat>/<brand>/<product>/01.png -> <product>/01.png
            echo "$rel" | cut -d/ -f3-
            ;;
        *)
            # pedals/epiphone/foo/01.png -> foo/01.png
            echo "$rel" | cut -d/ -f2-
            ;;
    esac
}

mkdir -p "$OUT"

for cat in "${CATS[@]}"; do
    cat_dir="$BACKUP/$cat"
    if [ ! -d "$cat_dir" ]; then
        echo "WARN: categoria $cat no existe en backup, skip"
        continue
    fi

    # Encontrar todos los PNGs
    while IFS= read -r -d '' png; do
        # Relativo al backup root
        rel_to_root=$(echo "$png" | sed "s|^$BACKUP/||")
        # Relativo a la categoria
        rel_to_cat=$(echo "$rel_to_root" | cut -d/ -f2-)

        brand=$(extract_brand "$cat" "$rel_to_cat")
        subpath=$(extract_subpath "$cat" "$rel_to_cat")

        if [ -z "$brand" ] || [ -z "$subpath" ]; then
            echo "SKIP estructura no reconocida: $png"
            continue
        fi

        # Genera 4 sizes por PNG
        for size in "${SIZES[@]}"; do
            # ${size}x${size} (todos los productos del backup son cuadrados
            # para amps, pedals, capos, etc. Si hay rectangulares, este
            # formato se ajustaria en el filename).
            out_file="$OUT/$brand/${subpath%.png}-${size}x${size}.webp"
            out_dir=$(dirname "$out_file")

            if [ -f "$out_file" ]; then
                TOTAL_SKIPPED=$((TOTAL_SKIPPED + 1))
                continue
            fi

            mkdir -p "$out_dir"
            if cwebp -q "$QUALITY" -resize "$size" 0 "$png" -o "$out_file" 2>/dev/null; then
                TOTAL_GENERATED=$((TOTAL_GENERATED + 1))
            else
                echo "  ✗ cwebp failed: $png"
            fi
        done
    done < <(find "$cat_dir" -name '*.png' -type f -print0)

    cat_count=$(find "$cat_dir" -name '*.png' -type f 2>/dev/null | wc -l)
    echo "  ✓ $cat: $cat_count PNGs encontrados"
done

echo ""
echo "=== Done ==="
echo "Generated: $TOTAL_GENERATED webps"
echo "Skipped:   $TOTAL_SKIPPED (ya existian)"
