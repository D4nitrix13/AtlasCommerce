#!/usr/bin/env bash
#
# fetch-catalog-images.sh — descarga imágenes de producto para los
# catálogos de Panda Estampados y Kitsune y las normaliza a la misma
# convención que las fotos de La Voz.
#
# SALIDA (idéntica a assets/products/ de La Voz):
#   assets/products/{tenant}/{brand}/{producto}/{NN}-{rol}-{WxH}.webp
#   NN  = 01 front, 02 back (más 03, 04… si hay más vistas)
#   WxH = 1600, 1200, 800, 400 de ancho (alto proporcional al source)
#
# USO:
#   ./scripts/fetch-catalog-images.sh scripts/product-catalog.json
#   TENANT=panda-estampados ./scripts/fetch-catalog-images.sh <catalogo>
#   DRY_RUN=true ./scripts/fetch-catalog-images.sh <catalogo>
#
# Fuente de imágenes: Wikimedia Commons (licencias libres, sin API key).
# Las que bajan son fotos reales de producto/objeto; para producción
# conviene reemplazarlas por fotos del propio cliente con el mismo
# nombre de archivo.
#
# Requiere: curl, jq, magick (ImageMagick), file.

set -euo pipefail

CATALOG="${1:?uso: fetch-catalog-images.sh <product-catalog.json>}"
TENANT_FILTER="${TENANT:-}"
DRY_RUN="${DRY_RUN:-false}"
MIN_WIDTHS=(1600 1200 800 400)
QUALITY=85
DEST_BASE="assets/products"
COMMONS_API="https://commons.wikimedia.org/w/api.php"
USER_AGENT="AtlasCommerceCatalogBot/1.0 (dev prototype)"
MAX_PER_PRODUCT=2

die() {
  echo "Error: ${1}" >&2
  exit 1
}

log() {
  echo "[$(date +'%H:%M:%S')] ${1}" >&2
}

run() {
  if [[ "${DRY_RUN}" == "true" ]]; then
    echo "  [dry-run] ${*}" >&2
    return 0
  fi
  "${@}"
}

check_deps() {
  local cmd
  for cmd in curl jq magick file; do
    command -v "${cmd}" >/dev/null 2>&1 || die "${cmd} es requerido y no está instalado"
  done
}

[[ -f "${CATALOG}" ]] || die "catálogo no encontrado: ${CATALOG}"

check_deps

# ── descarga un binario a un destino, verificando que sea imagen real ──
download_image() {
  local url="${1}" dest="${2}"
  curl -sSL --fail --max-time 30 -A "${USER_AGENT}" -o "${dest}" "${url}" || return 1
  file "${dest}" | grep -qiE "image|bitmap|JPEG|PNG|WebP" || return 1
  return 0
}

# ── convierte un source a los tamaños de la convención, con rol e índice ──
# Crop cuadrado centrado (igual que las fotos scrapeadas de La Voz:
# `01-body-1600x1600.webp`), para que el nombre sea predecible y la
# factory del frontend pueda armar la URL sin leer un manifest.
convert_to_webp() {
  local src="${1}" out_dir="${2}" base="${3}" role="${4}" index="${5}"
  local w out tmp
  tmp=$(mktemp -d)
  for w in "${MIN_WIDTHS[@]}"; do
    out="${out_dir}/${base}-${role}-${w}x${w}-${index}.webp"
    run magick "${src}" \
      -resize "${w}x${w}^" -gravity center -extent "${w}x${w}" \
      -quality "${QUALITY}" "${tmp}/sq.webp" || {
      echo "  ✗ crop falló: ${out}" >&2
      continue
    }
    run mv "${tmp}/sq.webp" "${out}"
  done
  rm -rf "${tmp}"
}

# ── busca candidatos libres en Wikimedia Commons ──
search_commons() {
  local terms="${1}" limit="${2}"
  curl -sS --fail --max-time 20 -A "${USER_AGENT}" --get "${COMMONS_API}" \
    --data-urlencode "action=query" \
    --data-urlencode "format=json" \
    --data-urlencode "generator=search" \
    --data-urlencode "gsrsearch=${terms}" \
    --data-urlencode "gsrnamespace=6" \
    --data-urlencode "gsrlimit=${limit}" \
    --data-urlencode "prop=imageinfo" \
    --data-urlencode "iiprop=url|mime" \
    --data-urlencode "iiurlwidth=1600" \
    | jq -r '
        (.query.pages // {})
        | to_entries[]
        | .value.imageinfo[0]
        | select(. != null)
        | select(.mime == "image/jpeg" or .mime == "image/png")
        | .thumburl // .url
      '
}

TOTAL=0
DOWNLOADED=0
FAILED=0

products=$(jq -c '.[]' "${CATALOG}")
while IFS= read -r product; do
  tenant=$(jq -r '.tenant' <<<"${product}")
  brand=$(jq -r '.brand' <<<"${product}")
  slug=$(jq -r '.slug' <<<"${product}")
  name=$(jq -r '.name' <<<"${product}")

  if [[ -n "${TENANT_FILTER}" && "${tenant}" != "${TENANT_FILTER}" ]]; then
    continue
  fi

  out_dir="${DEST_BASE}/${tenant}/${brand}/${slug}"
  log "→ ${tenant}/${brand}/${slug} (${name})"

  # Los términos de búsqueda por producto + el de cada acabado.
  base_terms=$(jq -r '.searchTerms | join(" ")' <<<"${product}")
  finishes=$(jq -c '.finishes[]?' <<<"${product}")

  index=1
  per_product=0
  while IFS= read -r finish; do
    [[ -z "${finish}" ]] && continue
    [[ "${per_product}" -ge "${MAX_PER_PRODUCT}" ]] && break

    finish_terms=$(jq -r '.searchTerms // [] | join(" ")' <<<"${finish}")
    query="${base_terms} ${finish_terms}"

    mapfile -t urls < <(search_commons "${query}" 4)
    if [[ "${#urls[@]}" -eq 0 ]]; then
      echo "  ✗ sin resultados: ${query}" >&2
      FAILED=$((FAILED + 1))
      continue
    fi

    tmp=$(mktemp -d)
    got=0
    for url in "${urls[@]}"; do
      [[ "${got}" -ge 1 ]] && break
      src="${tmp}/src.bin"
      if download_image "${url}" "${src}"; then
        role="front"
        [[ "${index}" -gt 1 ]] && role="back"
        idx=$(printf "%02d" "${index}")
        run mkdir -p "${out_dir}"
        if [[ "${DRY_RUN}" != "true" ]]; then
          convert_to_webp "${src}" "${out_dir}" "${slug}" "${role}" "${idx}" || true
        else
          echo "  [dry-run] convert ${url} → ${out_dir}/${slug}-${role}-*-${idx}.webp" >&2
        fi
        got=1
        DOWNLOADED=$((DOWNLOADED + 1))
        index=$((index + 1))
        per_product=$((per_product + 1))
      fi
    done
    rm -rf "${tmp}"
    [[ "${got}" -eq 0 ]] && FAILED=$((FAILED + 1))
  done <<<"${finishes}"

  TOTAL=$((TOTAL + 1))
done <<<"${products}"

echo ""
log "Productos procesados: ${TOTAL} · vistas descargadas: ${DOWNLOADED} · fallos: ${FAILED}"
[[ "${DRY_RUN}" == "true" ]] && log "(dry-run: no se escribió nada)"
exit 0
