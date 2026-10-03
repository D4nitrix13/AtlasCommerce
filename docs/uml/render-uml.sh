#!/usr/bin/env bash
# ============================================================
# Script: render-uml.sh
# Renderiza los diagramas PlantUML del Avance 3 a PNG, SVG y PDF.
# Requisito: Docker corriendo (la imagen plantuml/plantuml trae
# Java + Graphviz, sin instalar nada en el host).
#
# Uso:
#   ./render-uml.sh              # PNG + SVG + PDF en docs/uml/build/
#   ./render-uml.sh --clean      # limpia build/ y re-renderiza
#   ./render-uml.sh --png-only   # solo PNG
#   ./render-uml.sh --svg-only   # solo SVG (recomendado para documentos)
#   ./render-uml.sh --pdf-only   # solo PDF
# ============================================================

set -euo pipefail

UML_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${UML_DIR}/build"
PUML_FILES=("${UML_DIR}"/*.puml)

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

DO_SVG=1
DO_PDF=1
DO_CLEAN=0

for arg in "$@"; do
  case "${arg}" in
    --svg-only) DO_PDF=0 ;;
    --pdf-only) DO_SVG=0 ;;
    --clean)    DO_CLEAN=1 ;;
    *) echo -e "${RED}Argumento desconocido: ${arg}${NC}"; exit 1 ;;
  esac
done

if [[ ${DO_CLEAN} -eq 1 ]]; then
  echo -e "${YELLOW}Limpiando build/ y archivos previos...${NC}"
  rm -rf "${BUILD_DIR}"
  rm -f "${UML_DIR}"/*.svg "${UML_DIR}"/*.pdf
fi

if [[ ${#PUML_FILES[@]} -eq 0 ]]; then
  echo -e "${RED}No se encontraron archivos .puml en ${UML_DIR}${NC}"
  exit 1
fi

mkdir -p "${BUILD_DIR}/svg" "${BUILD_DIR}/pdf"

echo -e "${GREEN}Renderizando ${#PUML_FILES[@]} diagramas con PlantUML (Docker)...${NC}"
[[ ${DO_SVG} -eq 1 ]] && echo -e "  Formato: SVG ${GREEN}(vectorial, recomendado para documentos)${NC}"
[[ ${DO_PDF} -eq 1 ]] && echo "  Formato: PDF"
echo ""

render_one() {
  local puml="$1"
  local ext="$2"
  local puml_name
  puml_name=$(basename "${puml}" .puml)
  local marker
  marker=$(mktemp)

  docker run --rm \
    -v "${UML_DIR}:/data" \
    -w /data \
    plantuml/plantuml \
    "-t${ext}" -failfast2 -nometadata \
    "/data/$(basename "${puml}")" \
    > /dev/null 2>&1

  local found
  found=$(find "${UML_DIR}" -maxdepth 1 -name "*.${ext}" -newer "${marker}" -print -quit)
  rm -f "${marker}"

  if [[ -n "${found}" && -f "${found}" ]]; then
    mv "${found}" "${BUILD_DIR}/${ext}/${puml_name}.${ext}"
    return 0
  else
    return 1
  fi
}

for puml in "${PUML_FILES[@]}"; do
  puml_name=$(basename "${puml}" .puml)
  echo -n "  → ${puml_name} ... "

  rc=0
  if [[ ${DO_SVG} -eq 1 ]]; then
    render_one "${puml}" svg || rc=$?
  fi
  if [[ ${DO_PDF} -eq 1 ]]; then
    render_one "${puml}" pdf || rc=$?
  fi

  if [[ ${rc} -eq 0 ]]; then
    echo -e "${GREEN}OK${NC}"
  else
    echo -e "${YELLOW}parcial${NC} (algunos formatos fallaron)"
  fi
done

echo ""
echo -e "${GREEN}Listo.${NC}"
echo "  SVGs: ${BUILD_DIR}/svg/ ($(ls "${BUILD_DIR}/svg/" 2>/dev/null | wc -l) archivos)"
echo "  PDFs: ${BUILD_DIR}/pdf/ ($(ls "${BUILD_DIR}/pdf/" 2>/dev/null | wc -l) archivos)"
echo ""
echo "Archivos generados:"
for d in svg pdf; do
  if [[ -d "${BUILD_DIR}/${d}" ]]; then
    ls -lh "${BUILD_DIR}/${d}/" 2>/dev/null | tail -n +2 | awk -v ext="${d}" '{printf "  %-8s  %s.%s\n", $5, $9, ext}'
  fi
done
echo ""
echo -e "${YELLOW}💡 Tip: Para el documento Word/LibreOffice usá los SVG,${NC}"
echo -e "${YELLOW}   escalan a cualquier tamaño sin perder calidad.${NC}"
