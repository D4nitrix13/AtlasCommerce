#!/usr/bin/env bash
# ===============================================================
# scripts/check-n-plus-one.sh
# ===============================================================
# Static analysis para detectar patrones N+1 en el backend Laravel.
#
# Un N+1 ocurre cuando:
#   1. Se itera sobre una colección de modelos Eloquent.
#   2. Dentro del loop, se accede a una relación (propiedad o método)
#      que dispara una query individual POR CADA item.
#
# Este script busca patrones sospechosos con regex y reporta líneas
# que requieren revisión manual. NO es un análisis estático perfecto
# (eso requiere PHPStan custom rules o Rector); es un smell detector
# conservador que ayuda a cazar N+1 evidentes.
#
# Suprimir falsos positivos:
#   - Agregar `@phpstan-ignore-next-line` (comentario) en la línea.
#   - O agregar el path:line a `.n-plus-one.ignore` (whitelist).
#
# Salidas:
#   - exit 0 si no encuentra patrones sospechosos.
#   - exit 1 si encuentra al menos uno.
#
# Uso:
#   ./scripts/check-n-plus-one.sh
#
# Variables:
#   SCAN_DIRS — directorios a escanear (default: backend/app).
# ===============================================================

set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
SCAN_DIRS="${SCAN_DIRS:-backend/app}"
IGNORE_FILE="${REPO_ROOT}/.n-plus-one.ignore"

# Colores (solo si la salida es TTY).
if [ -t 1 ]; then
    RED=$'\033[31m'
    YELLOW=$'\033[33m'
    GREEN=$'\033[32m'
    RESET=$'\033[0m'
else
    RED=""
    YELLOW=""
    GREEN=""
    RESET=""
fi

echo "=== N+1 Pattern Scanner ==="
echo "Directorios: ${SCAN_DIRS}"
echo ""

FOUND=0

# Denylist: nombres de atributos/métodos comunes en Eloquent que NO son
# relaciones. Si el foreach accede a uno de estos, NO es N+1.
DENYLIST_KEYWORDS='id|uuid|key|slug|status|name|first_name|last_name|full_name|email|phone|locale|code|value|number|hash|token|message|title|description|content|metadata|amount|balance|total|subtotal|unit_price|quantity|count|notes|type|reason|reference|address_type|address|country|department|municipality|detailed_address|maintenance_mode|is_active|is_primary|is_anonymized|is_approved|is_read|is_filterable|is_required|is_visible|is_default|is_maintenance|is_featured|currency_id|customer_id|user_id|store_id|product_id|category_id|brand_id|order_id|address_id|payment_id|invoice_id|parent_id|tax_rate|currency|created_at|updated_at|deleted_at|registered_at|expires_at|starts_at|ends_at|published_at|unpublished_at|sort_order|public_title|public_price|public_description|meta_title|meta_description|og_image_url|logo_path|favicon_path|cover_image_path|attributes|original|relations|casts|policies|table|fillable|guarded|connection|dates|timestamps|perPage|update|save|delete|refresh|load|with|fill|toArray|toJson|find|findOrFail|firstOrCreate|updateOrCreate|wasRecentlyCreated|trashed|forceDelete|restore|softDeletes|exists|wasChanged|isDirty|isClean|getKey|getAttributes|getOriginal|getChanges|setAttribute|getRelation|relationLoaded|touch|push|increment|decrement|active'

# Patrón regex compilado una vez: $VAR->relation() o $VAR->relation->
# (relación llamada como método o accedida para chained).
# Usamos $VAR->[a-z]+... para capturar cualquier camelCase.

for DIR in ${SCAN_DIRS}; do
    if [ ! -d "${REPO_ROOT}/${DIR}" ]; then
        echo "${YELLOW}Skipping non-existent dir: ${DIR}${RESET}"
        continue
    fi

    FOREACH_LINES=$(rg -n "foreach\s*\(" "${REPO_ROOT}/${DIR}" --type php 2>/dev/null || true)
    if [ -z "${FOREACH_LINES}" ]; then
        continue
    fi

    while IFS= read -r FOREACH_LINE; do
        [ -z "${FOREACH_LINE}" ] && continue

        FILE=$(echo "${FOREACH_LINE}" | cut -d: -f1)
        LINENUM=$(echo "${FOREACH_LINE}" | cut -d: -f2)

        VAR=$(echo "${FOREACH_LINE}" | sed -E 's/.*as[[:space:]]+[$]?([a-zA-Z_][a-zA-Z0-9_]*).*/\1/' | head -1)
        if [ -z "${VAR}" ] || [ "${VAR}" = "${FOREACH_LINE}" ]; then
            continue
        fi

        # Lee 15 líneas después del foreach.
        CONTEXT=$(sed -n "${LINENUM},$((LINENUM+15))p" "${FILE}" 2>/dev/null)
        if [ -z "${CONTEXT}" ]; then
            continue
        fi

        # Busca $VAR->algo() o $VAR->algo-> (chained).
        RELATION_PATTERN='\$'"${VAR}"'->[a-zA-Z][a-zA-Z0-9_]{2,}(?:[(]|-)'
        RAW_HITS=$(echo "${CONTEXT}" | rg -o "${RELATION_PATTERN}" 2>/dev/null | sort -u || true)
        if [ -z "${RAW_HITS}" ]; then
            continue
        fi

        # Filtra keywords comunes (atributos/métodos Eloquent).
        # Para cada hit, extrae el nombre del método y verifica si está en la denylist.
        FILTERED=""
        while IFS= read -r HIT; do
            [ -z "${HIT}" ] && continue
            # Extrae el nombre después de -> (sin () ni -> al final).
            KEY=$(echo "${HIT}" | sed -E 's/^\$[a-zA-Z_][a-zA-Z0-9_]*->//; s/[(]-?$//')
            # Si key está en denylist, skip.
            if echo "${KEY}" | rg -q "^(${DENYLIST_KEYWORDS})$"; then
                continue
            fi
            FILTERED="${FILTERED}${HIT}
"
        done <<< "${RAW_HITS}"

        HITS=$(echo "${FILTERED}" | rg -v '^$' || true)
        if [ -z "${HITS}" ]; then
            continue
        fi

        # Whitelist: comparar paths relativos al repo root.
        RELATIVE_FILE="${FILE#${REPO_ROOT}/}"
        WHITELIST_KEY="${RELATIVE_FILE}:${LINENUM}"
        if [ -f "${IGNORE_FILE}" ] && rg -qF "${WHITELIST_KEY}" "${IGNORE_FILE}" 2>/dev/null; then
            continue
        fi

        # Línea con @phpstan-ignore.
        LINE_CONTENT=$(sed -n "${LINENUM}p" "${FILE}" 2>/dev/null)
        if echo "${LINE_CONTENT}" | rg -qF "@phpstan-ignore" 2>/dev/null; then
            continue
        fi

        FOUND=$((FOUND + 1))
        echo "${RED}⚠ Posible N+1${RESET}: ${FILE}:${LINENUM}"
        echo "  ${YELLOW}foreach → \$VAR=\$${VAR}${RESET}"
        echo "  ${YELLOW}accesos detectados (no en denylist):${RESET}"
        echo "${HITS}" | sed 's/^/    /'
        echo ""
    done <<< "${FOREACH_LINES}"
done

echo "=== Resumen ==="
if [ "${FOUND}" -gt 0 ]; then
    echo "${RED}✗ ${FOUND} posible(s) N+1 detectado(s)${RESET}"
    echo ""
    echo "Para cada uno:"
    echo "  1. Confirmar manualmente si es N+1 real (¿la relación se eager-loadeó?)."
    echo "  2. Si ES N+1: agregar \`with(['relation'])\` al query antes del foreach."
    echo "  3. Si es falso positivo: agregar \`@phpstan-ignore-next-line\` o entry en .n-plus-one.ignore"
    exit 1
else
    echo "${GREEN}✓ No se detectaron patrones N+1 obvios${RESET}"
    exit 0
fi
