#!/usr/bin/env bash
#
# ingest-product-images.sh — ordena las fotos de producto que bajaste
# a mano y las normaliza a la convención de assets/, listas para
# `php artisan assets:seed-catalog` (que las hashea y sube al FTP).
#
# ENTRADA (la estructura que creás vos al descargar):
#   incoming/{tenant}/{brand}/{producto}/{variante}/{rol}.{jpg|png|webp}
#
#   rol: front | back | body | backbody | headstock | side | beauty |
#        pickup | knobs | case | finish-top | finish-back | binding |
#        fingerboard | ...
#        (ver .ai/rules/assets-naming-policy.md §3)
#
#   Ejemplo:
#     incoming/panda-estampados/panda/polo-pique/negro/front.jpg
#     incoming/panda-estampados/panda/polo-pique/negro/back.jpg
#     incoming/kitsune/good-smile/nendoroid/original/front.png
#
# SALIDA (convención canónica, ver assets-naming-policy §1):
#   assets/products/{tenant}/{brand}/{producto}/{variante}/{rol}-{WxH}.webp
#   con WxH = 1600, 1200, 800, 400 (crop cuadrado centrado, quality 85)
#
# USO:
#   ./scripts/ingest-product-images.sh                     # incoming/ por defecto
#   ./scripts/ingest-product-images.sh /ruta/a/mis-fotos
#   DRY_RUN=true ./scripts/ingest-product-images.sh        # solo reporta
#   SEED=true ./scripts/ingest-product-images.sh           # al final, seed-catalog
#
# Requiere: magick (ImageMagick). Con SEED=true también php + docker.

set -euo pipefail

INCOMING="${1:-incoming}"
ASSETS_ROOT="assets/products"
QUALITY=85
WIDTHS=(1600 1200 800 400)
DRY_RUN="${DRY_RUN:-false}"
SEED="${SEED:-false}"
# Roles válidos (mismo vocabulario que CatalogAssetsSeeder::ROLE_ORDER).
VALID_ROLES="front back body backbody headstock back-headstock pickup knobs side case beauty finish-top finish-back finish-sides binding fingerboard sn-label tailpiece finish-peghead"

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

command -v magick >/dev/null 2>&1 || die "magick (ImageMagick) es requerido"
[[ -d "${INCOMING}" ]] || die "no existe el directorio de entrada: ${INCOMING}"

# ── crop cuadrado centrado + 4 resoluciones WebP ──
normalize() {
  local src="${1}" out_dir="${2}" role="${3}"
  local w tmp out
  tmp=$(mktemp -d)
  for w in "${WIDTHS[@]}"; do
    out="${out_dir}/${role}-${w}x${w}.webp"
    run magick "${src}" \
      -resize "${w}x${w}^" -gravity center -extent "${w}x${w}" \
      -quality "${QUALITY}" "${tmp}/sq.webp" || {
      echo "  ✗ falló el crop de ${src}" >&2
      continue
    }
    run mv "${tmp}/sq.webp" "${out}"
  done
  rm -rf "${tmp}"
}

is_valid_role() {
  local role="${1}" r
  for r in ${VALID_ROLES}; do
    [[ "${r}" == "${role}" ]] && return 0
  done
  return 1
}

# ── recorre incoming/{tenant}/{brand}/{product}/{variant}/{role}.* ──
count=0
skipped=0

while IFS= read -r src; do
  # incoming/<tenant>/<brand>/<product>/<variant>/<file>
  rel="${src#"${INCOMING}"/}"
  IFS='/' read -r tenant brand product variant filename <<<"${rel}"

  if [[ -z "${tenant:-}" || -z "${brand:-}" || -z "${product:-}" || -z "${variant:-}" ]]; then
    echo "  ✗ ruta no reconocida (esperado 4 niveles): ${rel}" >&2
    skipped=$((skipped + 1))
    continue
  fi

  role="${filename%.*}"
  if ! is_valid_role "${role}"; then
    echo "  ✗ rol desconocido '${role}' en ${rel} (ver §3 de assets-naming-policy)" >&2
    skipped=$((skipped + 1))
    continue
  fi

  out_dir="${ASSETS_ROOT}/${tenant}/${brand}/${product}/${variant}"
  log "→ ${tenant}/${brand}/${product}/${variant}/${role}"
  run mkdir -p "${out_dir}"
  normalize "${src}" "${out_dir}" "${role}"
  count=$((count + 1))
done < <(find "${INCOMING}" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' -o -iname '*.avif' -o -iname '*.heic' \) | sort)

echo ""
log "Ingeridas: ${count} · salteadas: ${skipped}"

# ── validación de la regla assets-naming-policy §6 ──
if [[ "${DRY_RUN}" != "true" ]]; then
  bad_ext=$(find "${ASSETS_ROOT}" -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) | wc -l)
  bad_name=$(find "${ASSETS_ROOT}" -type d | grep -cE '[A-Z]|_' || true)
  log "Validación: extensiones no-webp=${bad_ext} · dirs con mayúscula/guion_bajo=${bad_name}"
  [[ "${bad_ext}" -gt 0 ]] && log "⚠ hay PNG/JPG en assets/ — la regla exige solo WebP"
  [[ "${bad_name}" -gt 0 ]] && log "⚠ hay dirs fuera de kebab-case — renombrar"
fi

# ── opcional: hashear + subir al FTP + poblar BD ──
if [[ "${SEED}" == "true" ]]; then
  log "Corriendo assets:seed-catalog (hashea y sube al FTP)…"
  ./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend \
    /usr/local/bin/php artisan assets:seed-catalog
fi

log "Listo. Para hashear y subir al FTP: SEED=true ./scripts/ingest-product-images.sh ${INCOMING}"
exit 0
