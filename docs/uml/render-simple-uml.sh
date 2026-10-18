#!/usr/bin/env bash
#
# render-simple-uml.sh
#
# Renderiza todos los archivos *-simple.puml a SVG y PDF verticales,
# aplica post-procesado para forzar viewBox A4 vertical si el ancho
# inicial es muy grande, y genera un reporte tabular en CSV.
#
# Salidas:
#   docs/uml/build/svg-simple/<nombre>-simple.svg
#   docs/uml/build/pdf-simple/<nombre>-simple.pdf
#   docs/uml/build/simple-report.md
#   docs/uml/build/simple-report.csv
#
set -euo pipefail

# --- Constantes y rutas ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
readonly UML_DIR="${SCRIPT_DIR}"
readonly BUILD_DIR="${UML_DIR}/build"
readonly SVG_OUT_DIR="${BUILD_DIR}/svg-simple"
readonly PDF_OUT_DIR="${BUILD_DIR}/pdf-simple"
readonly REPORT_MD="${BUILD_DIR}/simple-report.md"
readonly REPORT_CSV="${BUILD_DIR}/simple-report.csv"

# Dimensiones objetivo (ratio A4 vertical)
readonly TARGET_WIDTH=1600
readonly TARGET_HEIGHT=2200

# --- Funciones ---
die() {
  echo "Error: ${1}" >&2
  exit 1
}

log() {
  echo "[$(date +'%H:%M:%S')] ${1}" >&2
}

ensure_dirs() {
  [[ -d "${SVG_OUT_DIR}" ]] || mkdir -p "${SVG_OUT_DIR}"
  [[ -d "${PDF_OUT_DIR}" ]] || mkdir -p "${PDF_OUT_DIR}"
}

verify_docker() {
  command -v docker >/dev/null 2>&1 || die "docker not found"
  docker info >/dev/null 2>&1 || die "docker daemon not running"
}

# Renderiza un archivo puml a SVG y PDF
# Args:
#   $1 - ruta absoluta al archivo .puml
# Salidas: archivos renombrados en SVG_OUT_DIR y PDF_OUT_DIR
render_one() {
  local puml_file="${1}"
  local basename
  local workdir
  basename="$(basename "${puml_file}" .puml)"
  local svg_target="${SVG_OUT_DIR}/${basename}.svg"
  local pdf_target="${PDF_OUT_DIR}/${basename}.pdf"

  log "Renderizando ${basename}"

  workdir="$(mktemp -d)"
  # shellcheck disable=SC2064
  trap "rm -rf '${workdir}'" RETURN

  cp "${puml_file}" "${workdir}/"

  (cd "${workdir}" && \
    docker run --rm \
      -v "${workdir}:/data" \
      -w /data \
      plantuml/plantuml:latest \
      -tsvg -failfast2 -nometadata \
      "/data/${basename}.puml" \
      >/dev/null 2>"${workdir}/svg.err" \
  ) || {
    log "  [WARN] Fallo renderizando SVG de ${basename}: $(cat "${workdir}/svg.err" 2>/dev/null || echo unknown)"
    return 1
  }

  (cd "${workdir}" && \
    docker run --rm \
      -v "${workdir}:/data" \
      -w /data \
      plantuml/plantuml:latest \
      -tpdf -failfast2 -nometadata \
      "/data/${basename}.puml" \
      >/dev/null 2>"${workdir}/pdf.err" \
  ) || {
    log "  [WARN] Fallo renderizando PDF de ${basename}: $(cat "${workdir}/pdf.err" 2>/dev/null || echo unknown)"
    return 1
  }

  # PlantUML nombra la salida segun el @startuml title, no el archivo.
  # Encontrar lo que genero y renombrar al nombre esperado.
  local generated_svg
  local generated_pdf
  generated_svg="$(find "${workdir}" -maxdepth 1 -name '*.svg' -print -quit 2>/dev/null || true)"
  generated_pdf="$(find "${workdir}" -maxdepth 1 -name '*.pdf' -print -quit 2>/dev/null || true)"

  if [[ -n "${generated_svg}" && -f "${generated_svg}" ]]; then
    mv "${generated_svg}" "${svg_target}"
  else
    log "  [WARN] SVG no encontrado tras render de ${basename}"
    return 1
  fi
  if [[ -n "${generated_pdf}" && -f "${generated_pdf}" ]]; then
    mv "${generated_pdf}" "${pdf_target}"
  else
    log "  [WARN] PDF no encontrado tras render de ${basename} (continua sin PDF)"
  fi
}

# Post-procesa un SVG para forzar aspect-ratio vertical A4.
# Si el SVG es horizontal (ancho >= alto) o su ancho intrinseco
# supera MAX_WIDTH, fuerza width/height a TARGET_WIDTH x TARGET_HEIGHT
# con preserveAspectRatio="xMidYMid meet" para escalar sin distorsion.
# Args:
#   $1 - ruta absoluta al SVG
force_vertical_a4() {
  local svg_file="${1}"
  local header current_width current_height

  header="$(head -c 1500 "${svg_file}")"
  current_width="$(printf '%s' "${header}" | grep -oP '<svg[^>]*width="\K[0-9.]+' | head -n1 || echo 0)"
  current_height="$(printf '%s' "${header}" | grep -oP '<svg[^>]*height="\K[0-9.]+' | head -n1 || echo 0)"

  # Solo forzar si el SVG es horizontal (height <= width) O ancho > 1800
  # Si ya esta vertical dentro del rango, no tocar.
  awk -v w="${current_width}" -v h="${current_height}" \
    'BEGIN { exit (h >= w && w <= 1800) ? 0 : 1 }' && return 0

  # Forzar width/height SOLO en el primer tag <svg>...
  # Para evitar tocar rect/text internos con width/height propios.
  # awk de un solo paso es más preciso que sed con regex global.
  python3 - "${svg_file}" "${TARGET_WIDTH}" "${TARGET_HEIGHT}" << 'PYEOF'
import sys, re
path, tw, th = sys.argv[1], sys.argv[2], sys.argv[3]
with open(path) as f:
    content = f.read()
def repl(match):
    s = match.group(0)
    s = re.sub(r'width="[^"]*"', f'width="{tw}"', s, count=1)
    s = re.sub(r'height="[^"]*"', f'height="{th}"', s, count=1)
    s = re.sub(r'style="width:[0-9.]*px;height:[0-9.]*px',
               f'style="width:{tw}px;height:{th}px', s, count=1)
    if 'preserveAspectRatio' in s:
        s = re.sub(r'preserveAspectRatio="[^"]*"',
                   'preserveAspectRatio="xMidYMid meet"', s, count=1)
    else:
        s = s.replace('<svg', '<svg preserveAspectRatio="xMidYMid meet"', 1)
    return s
new = re.sub(r'<svg\s[^>]*>', repl, content, count=1)
with open(path, 'w') as f:
    f.write(new)
PYEOF
}

# Mide dimensiones finales del SVG (solo del tag root <svg>)
# Output: imprime "WIDTHxHEIGHT"
measure_svg() {
  local svg_file="${1}"
  local header w h
  header="$(head -c 1500 "${svg_file}")"
  w="$(printf '%s' "${header}" | grep -oP '<svg[^>]*width="\K[0-9.]+' | head -n1 || echo 0)"
  h="$(printf '%s' "${header}" | grep -oP '<svg[^>]*height="\K[0-9.]+' | head -n1 || echo 0)"
  echo "${w}x${h}"
}

# Cuenta elementos visuales (heuristica para reportar reduccion)
count_elements() {
  local puml_file="${1}"
  local count=0
  local n

  n="$(grep -E '^\s*(abstract\s+)?class\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^\s*interface\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^\s*enum\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^actor\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^\s*usecase\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^\s*participant\s+' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))
  n="$(grep -E '^\s*state\s+"' "${puml_file}" 2>/dev/null | wc -l | tr -d ' ')"
  count=$(( count + ${n:-0} ))

  echo "${count}"
}

# --- Programa principal ---
main() {
  ensure_dirs
  verify_docker

  local files=()
  while IFS= read -r -d '' f; do
    files+=("${f}")
  done < <(find "${UML_DIR}" -maxdepth 1 -name '*-simple.puml' -print0 | sort -z)

  [[ ${#files[@]} -gt 0 ]] || die "no se encontraron archivos *-simple.puml en ${UML_DIR}"

  log "Encontrados ${#files[@]} archivos -simple.puml"

  echo "original,simple,svg_w,svg_h,aspect,elements_simple,status" > "${REPORT_CSV}"

  local vertical_count=0
  local error_count=0
  local rendered_count=0

  for puml in "${files[@]}"; do
    local bn
    local original
    local svg_file
    bn="$(basename "${puml}" .puml)"
    original="${bn%-simple}.puml"
    svg_file="${SVG_OUT_DIR}/${bn}.svg"

    log "Procesando ${bn}"

    if render_one "${puml}"; then
      rendered_count=$(( rendered_count + 1 ))

      if [[ -f "${svg_file}" ]]; then
        force_vertical_a4 "${svg_file}" || true
      fi

      local dims_final
      local w h aspect elements
      dims_final="$(measure_svg "${svg_file}")"
      w="${dims_final%x*}"
      h="${dims_final#*x}"

      aspect="$(awk -v a="${w}" -v b="${h}" 'BEGIN{if(b>0 && a>0) printf "%.2f", b/a; else print "0"}')"

      if (( $(echo "${h} >= ${w}" | bc -l 2>/dev/null || echo 0) )); then
        vertical_count=$(( vertical_count + 1 ))
      fi

      elements="$(count_elements "${puml}")"

      echo "${original},${bn}.puml,${w},${h},${aspect},${elements},OK" >> "${REPORT_CSV}"
    else
      error_count=$(( error_count + 1 ))
      echo "${original},${bn}.puml,0,0,0,0,ERROR" >> "${REPORT_CSV}"
    fi
  done

  {
    echo "# Reporte de Simplificacion UML - AtlasCommerce"
    echo
    echo "_Generado: $(date '+%Y-%m-%d %H:%M:%S')_"
    echo
    echo "## Resultado por diagrama"
    echo
    echo "| Archivo original | Archivo simple | SVG (ancho x alto px) | Aspect ratio | Reduccion elementos | Estado |"
    echo "|---|---|---|---|---|---|"

    while IFS=',' read -r orig simp w h asp el status; do
      [[ "${orig}" == "original" ]] && continue
      echo "| \`${orig}\` | \`${simp}\` | ${w}x${h} | ${asp} | ${el} elem | ${status} |"
    done < "${REPORT_CSV}"

    echo
    echo "## Resumen"
    echo
    echo "- Total archivos: ${rendered_count}/16 creados (errores: ${error_count})"
    echo "- SVGs verticales (alto >= ancho): ${vertical_count}/16"
    if [[ ${error_count} -eq 0 ]]; then
      echo "- Errores de renderizado: ninguno"
    else
      echo "- Errores de renderizado: ${error_count} archivo(s)"
    fi
  } > "${REPORT_MD}"

  log "Reporte generado en:"
  log "  ${REPORT_MD}"
  log "  ${REPORT_CSV}"
}

main "$@"
