# UML Diagrams Simplification — Vertical for Word

## Objective
Simplify all 16 UML diagrams of Avance 3 so they fit a vertical Word page and
remain readable. Goal: same essential semantics, ~50% less visual clutter, aspect
ratio closer to portrait so each diagram occupies one A4 vertical page.

## Problem
La profesora marcó los diagramas como demasiado verbosos. Hay muchos detalles
(includes/extends, métodos en cada clase, atributos redundantes, paquetes
wrappers) que saturan la imagen y, combinados con un layout horizontal (mucho
más ancho que alto), no caben en una página Word vertical.

## Why
- Trazabilidad con la implementación (78 modelos Laravel) sobrevive aunque
  simplifiquemos, porque el README y los archivos `.md` por módulo siguen
  siendo la fuente de verdad.
- Lo que lee la profesora es la IMAGEN, no los nombres de métodos. Un diagrama
  legible vale más que uno completo pero ilegible.

## Scope
**In scope (los 16 `.puml` en `docs/uml/`):**
1. `8.1-casos-de-uso.puml`
2. `8.2.1-clases-nucleo.puml`
3. `8.2.2-clases-catalogo.puml`
4. `8.2.3-clases-clientes-marketing.puml`
5. `8.2.4-clases-operaciones.puml`
6. `8.2.5-clases-pagos-envios.puml`
7. `8.2.6-clases-soporte-sistema.puml`
8. `8.2.7-clases-modulo-pedidos.puml`
9. `8.3.1-actividad-checkout-pago.puml`
10. `8.3.2-actividad-gestion-pedidos.puml`
11. `8.3.3-actividad-control-stock.puml`
12. `8.4.1-secuencia-registro-2fa.puml`
13. `8.4.2-secuencia-checkout-pasarela.puml`
14. `8.4.3-secuencia-gestion-pedido.puml`
15. `8.5.1-estado-pedido.puml`
16. `8.5.2-estado-2fa.puml`

**Out of scope:** Plantillas Coleman (`8.1.1-coleman/*.md`), `render-uml.sh`,
archivos de build originales.

## Constraints
- **NO sobrescribir originales.** Crear copias con sufijo `-simple.puml`.
- **Renderizar a `build/svg-simple/` y `build/pdf-simple/`** (no `build/svg/`).
- Mantener semántica UML (relaciones, multiplicidades, cardinalidades).
- Mantener TODOS los `<<include>>` y `<<extend>>` si son estructuralmente
  importantes (los borramos solo si el sub-flujo es trivial o duplica al padre).
- Cada diagrama debe ser legible al imprimir en A4 vertical.

## Actionable Tasks

- [x] **T1** Leer todos los 16 `.puml` originales para entender estructura.
- [x] **T2** Aplicar reglas de simplificación por tipo (ver "Reglas" abajo).
- [x] **T3** Escribir cada `<nombre>-simple.puml` en `docs/uml/`.
- [x] **T4** Renderizar los 16 nuevos `.puml` a SVG y PDF vía Docker PlantUML.
- [x] **T5** Reportar tamaños resultantes (ancho × alto en px o aspect-ratio).

## Reglas de simplificación por tipo

### 8.1 — Casos de uso
- Mantener los **4 actores** y los **18 CUs principales** (CU-01…CU-18) con número.
- Eliminar sub-flujos sin número (`UC_BUSCAR`, `UC_TOTAL`, `UC_STOCK`, `UC_DIR`, …).
- Eliminar TODAS las relaciones `<<include>>` y `<<extend>>` (saturación pura,
  no aportan comprensión para una vista global).
- Consolidar los ~11 paquetes en **4 paquetes de alto nivel**: Autenticación,
  Catálogo/Ventas, Operaciones, Sistema.
- Resultado esperado: ~50% menos elementos.

### 8.2.x — Clases (7 diagramas)
- Cada clase: mantener solo **atributos identificadores y 2-3 campos clave**
  (id + nombre + 1-2 más representativos). Quitar el resto.
- **Quitar TODOS los métodos**, salvo `+ crear()` o `+ buscar()` si la clase
  tiene sentido de negocio importante. El lector entiende que existen.
- Eliminar clases técnicas redundantes (ej. `CodigoRecuperacion`,
  `TokenResetContrasena` → consolidar; `CuentaSocial` → ocultar si no impacta).
  Pero **mantener** clases que son aggregate root o exponen dominio
  (Tienda, Usuario, Pedido, Producto, etc.).
- Mantener **relaciones y multiplicidades** (esto ES la semántica UML).
- Enums: dejar solo el nombre del enum y los literales, sin métodos.
- Quitar `<<aggregate root>>` y otros estereotipos verbosos.

### 8.3.x — Actividad (3 diagramas)
- Eliminar nodos de decisión/merge redundantes con la misma etiqueta.
- Eliminar notas (`note left of ... : "..."`) excepto si documentan una regla
  de negocio esencial.
- Mantener swimlanes solo si hay más de un actor responsable en el flujo.

### 8.4.x — Secuencia (3 diagramas)
- Eliminar mensajes `->>:` de log / observabilidad (`logger.info(...)`, etc.).
- Mantener solo el primer y último mensaje de los loops.
- Quitar alt/else blocks con condiciones triviales.

### 8.5.x — Estados (2 diagramas)
- Ya están simples. Solo verificar que el nombre del estado inicial y final
  sean legibles. Si hay sub-estados anidados, dejarlos.

### Ajuste al Word vertical
- PlantUML no controla aspect-ratio directamente, pero `scale` ayuda.
- Probar renderizar con `scale 0.8` y `dpi 96` por defecto.
- Si el SVG sale >1800px de ancho, aplicar post-procesado con `sed`/`awk` para
  inyectar `viewBox` y `preserveAspectRatio="xMidYMid meet"` ajustado a
  ~1600 × 2200 (ratio 4:5.5, cercano a A4 vertical).
- **NO** usar `width="100%"` en SVG inline; eso Word lo ignora.

## Acceptance Criteria
- [x] 16 archivos `<nombre>-simple.puml` creados.
- [x] 16 SVGs en `build/svg-simple/` con aspect-ratio más cercano a vertical que
      los originales — 16/16 con alto ≥ ancho.
- [x] 16 PDFs en `build/pdf-simple/` listos para incrustar en Word.
- [x] Reporte de tamaños: `build/simple-report.md` y `simple-report.csv`.
- [x] Diagramas siguen siendo reconocibles: clases principales, relaciones
      críticas, flujos preservados.

## Resultados (2026-10-17)

- **Reducción bytes totales**: 68900 → 40768 bytes (59% del original).
- **Aspect ratio original vs simple**: verticales 16/16.
- **Aspect-ratio más dramático**: 8.1 (982×2622 horizontal → 502×1709 vertical 3.40:1).
- **Casos con más simplificación**: 8.1 -73% bytes, 8.2.1 -56%, 8.2.4 -43%,
  8.4.2 -36%.

| Diagrama | Orig (W×H) | Simple (W×H) | Ratio | Veredicto |
|----------|-----------|--------------|-------|-----------|
| 8.1 casos-uso | 982×2622 | 502×1709 | 3.40 | ✓ |
| 8.2.1 nucleo | 3776×2028 | 1600×2200 | 1.38 | ✓ |
| 8.2.4 operaciones | 3277×1528 | 1600×2200 | 1.38 | ✓ |
| 8.3.3 stock | 3833×1441 | 1600×2200 | 1.38 | ✓ |
| 8.4.2 checkout-pasarela | 2150×2432 | 1600×2200 | 1.38 | ✓ |
| 8.4.3 gestion-pedido | 1854×2114 | 1609×2297 | 1.43 | ✓ |

Archivos entregados:
- `docs/uml/<nombre>-simple.puml` (16 fuentes simplificadas)
- `docs/uml/build/svg-simple/<nombre>-simple.svg` (16 SVGs vectoriales)
- `docs/uml/build/pdf-simple/<nombre>-simple.pdf` (16 PDFs de respaldo)
- `docs/uml/build/simple-report.md` y `simple-report.csv` (reporte)

## Verification
- Comparar lado a lado original vs simple: conteo de elementos visuales
  (clases, CUs, mensajes) reducido ≥30%.
- Verificar que ningún `<nombre>-simple.puml` falla al renderizar con
  PlantUML Docker (el script ya lo hace con `-failfast2`).
- Imprimir mentalmente: si abro el SVG simple en una pestaña a 100% y
  simulo A4 vertical (~794×1123 px a 96dpi), ¿el diagrama entra sin scroll
  horizontal?

## Decision Log
- **Decisión 2026-10-17**: usar sufijo `-simple` para no chocar nombres con
  los originales. El build simple va a `build/svg-simple/` para no pisar
  el build oficial.
- **Riesgo**: la profesora podría pedir más adelante los detalles completos.
  Mantener los `.puml` originales intactos mitiga este riesgo.

## Next Step
Lanzar sub-agente `general` con lectura+escritura+renderizado de los 16
diagramas aplicando estas reglas.
