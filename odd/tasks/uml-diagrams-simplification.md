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
- Eliminar sub-flujos sin número (`UC_BUSCAR`, `UC_TOTAL`, `UC_STOCK`,
  `UC_DIR`, …).
- Eliminar TODAS las relaciones `<<include>>` y `<<extend>>` (saturación
  pura, no aportan comprensión para una vista global).
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

---

# Ronda 2 — Ultra-simplificación de los diagramas de clases

## Objective

Los 7 diagramas de clases (8.2.1–8.2.7) siguen siendo verbosos: 8–12 clases
por diagrama, 3–5 atributos y 3–4 métodos por clase. Reducirlos al mínimo
absoluto que todavía comunique el modelo: solo nombres de clase y relaciones.

## Problem

La primera ronda quitó métodos pero dejó muchos atributos y conservó clases
técnicas secundarias. La aclaración de la profesora es explícita: **quitar
entidades y atributos**, simplificar al máximo.

## Scope

**In scope (7 archivos):** `docs/uml/8.2.{1..7}-clases-*-simple.puml`

**Out of scope:** los 9 diagramas no-clase (8.1, 8.3.x, 8.4.x, 8.5.x), los
`.puml` originales sin sufijo, `9.1-diagrama-er-simple.puml`.

## Actionable Tasks

- [x] **T6** Reescribir los 7 `8.2.x-*-simple.puml` al mínimo.
- [x] **T7** Renderizar vía `docs/uml/render-simple-uml.sh` (Docker PlantUML).
- [x] **T8** Verificar en `build/simple-report.csv` que los 7 rinden OK y
      que el conteo de elementos bajó.

## Reglas de la ronda 2

- Máximo **5 clases** por diagrama (borra el resto, incluidas las `<<internal>>`).
- **Cero atributos** y **cero métodos**. Las clases quedan como cajas con nombre
  (`class Producto`, sin bloque `{ }`).
- Conservar únicamente las relaciones **entre las clases que sobreviven**, con
  sus multiplicidades y etiquetas originales.
- Sin `package` (con ≤5 cajas el agrupamiento es ruido visual).
- Mantener el `@startuml <mismo título>`, los `skinparam` actuales, `hide circle`
  y `@enduml`.
- El `.puml` original (sin sufijo) queda intacto como respaldo.

## Verification

- `build/simple-report.csv` con las 7 filas en `OK`.
- Conteo de elementos por diagrama menor al de la ronda 1 (ver
  `build/simple-report.csv` del commit anterior).
- Ningún `8.2.x-*-simple.puml` debe referenciar una clase que ya no existe.

## Decision Log

- **Decisión 2026-10-21**: segunda ronda sobre los mismos archivos `-simple`
  (no se crea un sufijo `-minimal`) porque la profesora pidió simplificar más,
  no más variantes.
- **Riesgo**: con cero atributos el diagrama ya no sirve como especificación de
  datos. Aceptado: `docs/database/` y los modelos Laravel siguen siendo la
  fuente de verdad del detalle.

## Resultados (2026-10-21)

- **Clases**: 112 → 33 (5 por diagrama, salvo 8.2.3 y 8.2.4 con 4).
- **Relaciones**: 135 → 26.
- **Atributos y métodos**: 0 en los 7 diagramas.
- **Bytes de los 7 `.puml`**: 29723 → 3296 (−89% contra la ronda 1;
  −93% contra los originales sin sufijo, 44331 bytes).

| Diagrama | Clases | Relaciones | SVG | Estado |
|----------|--------|-----------|-----|--------|
| 8.2.1 núcleo | 5 | 4 | 247×292 | OK |
| 8.2.2 catálogo | 5 | 5 | 1600×2200 | OK |
| 8.2.3 clientes/marketing | 4 | 3 | 199×258 | OK |
| 8.2.4 operaciones | 4 | 3 | 222×276 | OK |
| 8.2.5 pagos/envíos | 5 | 3 | 1600×2200 | OK |
| 8.2.6 soporte/sistema | 5 | 4 | 114×456 | OK |
| 8.2.7 módulo pedidos | 5 | 4 | 1600×2200 | OK |

Ajustes manuales post-delegación:

- Se eliminaron dos cajas huérfanas sin ninguna relación: `Cupon` (8.2.3) y
  `RegistroInventario` (8.2.4). No se inventó una relación para justificarlas.
- Se unificó la multiplicidad `"many"` a `"*"` en las 4 relaciones de 8.2.7,
  que era la única inconsistencia de sintaxis heredada del original.

## Next Step

Los diagramas están sin commitear en `master`. Falta decidir rama y commit.

---

# Ronda 3 — Devolver PK y FKs a los diagramas de clases

## Objective

La ronda 2 quitó los atributos por completo y quedó un diagrama de cajas
sueltas. La profesora pidió un piso: **3 a 5 atributos por clase, priorizando
PK y FKs**. Se mantiene todo lo demás de la ronda 2 (5 clases, 0 métodos,
sin `package`).

## Problem

Un diagrama de clases sin PK ni FK pierde el 80% del mensaje: no se ve quién
apunta a quién ni qué identifica a cada entidad. La simplificación extrema
de la ronda 2 se pasó desimplificada.

## Scope

**In scope (mismos 7 archivos):** `docs/uml/8.2.{1..7}-clases-*-simple.puml`

**Out of scope:** los `.puml` originales, los 9 diagramas no-clase,
`9.1-diagrama-er-simple.puml`.

## Actionable Tasks

- [x] **T9** Agregar 3-5 atributos por clase, PK primero y luego FKs.
- [x] **T10** Re-renderizar vía `docs/uml/render-simple-uml.sh`.
- [x] **T11** Verificar en `build/simple-report.csv` que los 7 rinden OK.

## Reglas de la ronda 3

- **Tope 5 atributos, piso 3** por clase. Se cuentan solo atributos.
- Orden de prioridad:
  1. `- id: UUID` (PK) — siempre primero si existe.
  2. FKs `- <entidad>Id: UUID` — hasta 3.
  3. Si queda lugar, 1-2 campos de negocio (nombre, slug, precio, estado…).
- Los nombres y tipos se copian **literales del `.puml` original** de la
  misma clase. No inventar, no traducir, no "normalizar" a español.
- **Cero métodos**, como en la ronda 2.
- Si una clase real tiene menos de 3 columnas propias (subclases de
  herencia única, tablas de unión), se queda con las que tiene. No se rellena.
- Se conservan los 4-5 clases y las relaciones de la ronda 2 sin cambios.

## Verification

- `build/simple-report.csv` con las 7 filas en `OK`.
- Cada clase declarada tiene entre 3 y 5 atributos, salvo las excepciones
  justificadas del punto anterior.
- Cada atributo nuevo existe en el `.puml` original correspondiente.

## Decision Log

- **Decisión 2026-10-21**: ronda 3 sobre los mismos archivos `-simple`.
  La ronda 2 se conserva en el historial de git como referencia del mínimo
  absoluto que se probó y resultó insuficiente.
- **Riesgo**: entre 3 y 5 atributos × 5 clases el diagrama vuelve a crecer.
  Se acota con el tope de 5 por clase y sin métodos.

## Resultados (2026-10-21)

- **Atributos agregados**: 160. Reparto 25/25/25/20/21/19/25.
- **Clases**: 33 (sin cambios respecto a la ronda 2).
- **Métodos**: 0 (sin cambios).
- **Bytes de los 7 `.puml`**: 3296 → 6621. Sigue un **85% por debajo** de los
  originales sin sufijo (44331 bytes) y un 78% por debajo de la ronda 1.
- Render OK 7/7. Mayor SVG natural: 439×486 px.

| Diagrama | Clases | Atributos | SVG | Estado |
|----------|--------|-----------|-----|--------|
| 8.2.1 núcleo | 5 | 25 | 367×536 | OK |
| 8.2.2 catálogo | 5 | 21 | 1600×2200 | OK |
| 8.2.3 clientes/marketing | 4 | 19 | 439×486 | OK |
| 8.2.4 operaciones | 4 | 20 | 358×520 | OK |
| 8.2.5 pagos/envíos | 5 | 25 | 1600×2200 | OK |
| 8.2.6 soporte/sistema | 5 | 25 | 205×863 | OK |
| 8.2.7 módulo pedidos | 5 | 25 | 1600×2200 | OK |

Caso con menos de 3 atributos: `ProductoSimple` en 8.2.2, con 1 (`peso`).
Es subclase de herencia única de `Producto`, que ya aporta la PK; se decidió no
duplicar el `id` ni rellenarla.

Verificación de veracidad: un script comparó los 160 atributos contra el bloque
de la misma clase en el `.puml` original. 150 coinciden literalmente. Los 10
restantes son `Pedido` y `LineaPedido` en 8.2.6, clases que ese diagrama no
declara pero que sí define en `8.2.4-clases-operaciones.puml` con un bloque de
atributos idéntico. Cero atributos inventados.

## Next Step

Sin commitear en `master`. Falta decidir rama y commit.

---

# Ronda 4 — Devolver métodos a los diagramas de clases

## Objective

La ronda 2 dejó los diagramas sin métodos y las rondas 2-3 los repoblaron
solo de datos. Una clase sin operaciones es una tabla, no una clase: el
comportamiento es la mitad del mensaje de un diagrama de clases. Se pide
**al menos 3 métodos por clase**.

## Problem

El diagrama quedó reducido a "estructura de datos": 3-5 atributos y cero
comportamiento. La profesora pidió devolver las operaciones.

## Scope

**In scope (mismos 7 archivos):** `docs/uml/8.2.{1..7}-clases-*-simple.puml`

**Out of scope:** los `.puml` originales, los 9 diagramas no-clase,
`9.1-diagrama-er-simple.puml`.

## Actionable Tasks

- [x] **T12** Agregar ≥3 métodos por clase, copiados del `.puml` original.
- [x] **T13** Re-renderizar vía `docs/uml/render-simple-uml.sh`.
- [x] **T14** Verificar que ningún método sea inventado y que los 7 rinden OK.

## Reglas de la ronda 4

- **Mínimo 3 métodos** por clase. Objetivo 3-4; se toman más solo si la clase
  no tiene 3 operaciones propias.
- Se copian **literales del `.puml` original** de la misma clase: mismo nombre,
  misma firma completa con parámetros y tipo de retorno.
- Prioridad al elegir: operaciones de negocio (`+`) por sobre getters y
  consultas triviales.
- Preferencia (no regla dura): métodos cuya firma solo use primitivos, enums o
  clases que sigan en el diagrama. Si el método devuelve una clase eliminada,
  preferí otro salvo que sea la operación central del dominio.
- Atributos y clases **no cambian** respecto de la ronda 3.
- Si una clase no tiene 3 métodos reales, se queda con los que tiene y se
  reporta como excepción. No se inventa ninguno.

## Verification

- `build/simple-report.csv` con las 7 filas en `OK`.
- Cada clase declarada tiene ≥3 métodos, salvo excepciones reportadas.
- Cada método existe literalmente en el `.puml` original.

## Decision Log

- **Decisión 2026-10-21**: ronda 4 sobre los mismos archivos `-simple`.
- **Riesgo**: atributos + métodos en la misma caja es el escenario de
  saturación de la ronda 1. Se acota con topes por clase (5 atributos,
  3-4 métodos) y con el máximo de 5 clases por diagrama.

## Resultados (2026-10-21)

- **Métodos agregados**: 95. Reparto 15/15/12/11/14/14/14.
- **Atributos**: 160 (sin cambios). **Clases**: 33 (sin cambios).
- **Bytes de los 7 `.puml`**: 6621 → 9756. Sigue un **78% por debajo** de los
  originales sin sufijo (44331 bytes).
- Render OK 7/7. Con los métodos, 5 de los 7 diagramas pasan a ocupar el lienzo
  forzado 1600×2200; solo 8.2.1 (644×716) y 8.2.6 (410×1091) conservan tamaño
  natural.

| Diagrama | Clases | Atributos | Métodos | SVG | Estado |
|----------|--------|-----------|---------|-----|--------|
| 8.2.1 núcleo | 5 | 25 | 15 | 644×716 | OK |
| 8.2.2 catálogo | 5 | 21 | 15 | 1600×2200 | OK |
| 8.2.3 clientes/marketing | 4 | 19 | 12 | 1600×2200 | OK |
| 8.2.4 operaciones | 4 | 20 | 11 | 1600×2200 | OK |
| 8.2.5 pagos/envíos | 5 | 25 | 14 | 1600×2200 | OK |
| 8.2.6 soporte/sistema | 5 | 25 | 14 | 410×1091 | OK |
| 8.2.7 módulo pedidos | 5 | 25 | 14 | 1600×2200 | OK |

Clases con menos de 3 métodos (el original no define más): `Permiso` (8.2.1,
1), `ProductoSimple` (8.2.2, 1), `ItemListaDeseos` (8.2.3, 1), `ItemCarrito`
(8.2.4, 2), `LineaPedido` (8.2.4/8.2.6/8.2.7, 1), `TransaccionPago` (8.2.5, 2),
`ItemDevolucion` (8.2.6, 2).

Verificación de veracidad: los 95 métodos se compararon contra el conjunto de
métodos de las 16 clases de todos los `.puml` originales. **0 inventados.**

Fuentes cruzadas inevitablees: `8.2.7-clases-modulo-pedidos.puml` tiene
`hide methods` y no declara ninguno, y `8.2.6` no declara `Pedido` ni
`LineaPedido`. Esos métodos se tomaron de 8.2.4, 8.2.5 y 8.2.6.

## Hallazgo abierto (no corregido)

`8.2.7-clases-modulo-pedidos.puml` (original) usa `numeroPedido` y `estado`,
pero la migración real `2026_06_28_200200_create_orders_table.php` define
`order_number` y `status`, que es lo que usa `8.2.4`. Resultado: `Pedido`
aparece con nombres de columna distintos en dos figuras del mismo documento.
No se corrigió porque excede el alcance pedido; queda reportado para decisión.

## Next Step

Sin commitear en `master`. Falta decidir rama, commit y el hallazgo de 8.2.7.

---

# Ronda 5 — Simplificar los diagramas de actividad

## Objective

Aplicar a `8.3.1`, `8.3.2` y `8.3.3` la misma reducción que se aplicó a los
diagramas de clases en la ronda 2. Los diagramas de actividad no tienen
atributos ni métodos que devolver, así que acá "simplificar" es reducir nodos,
decisiones, carriles y bloques paralelos.

## Problem

La ronda 1 los dejó aceptables pero `8.3.3` mezcla dos flujos distintos
(operación manual de stock + validación automática en checkout) y rebota
constante de carril. El rebote de swimlane es el mayor ruido visual: obliga al
ojo a saltar horizontalmente por el diagrama.

## Scope

**In scope (3 archivos):** `docs/uml/8.3.{1,2,3}-actividad-*-simple.puml`

**Out of scope:** los `.puml` originales, los 7 diagramas de clases, los
diagramas de secuencia (8.4.x), de estados (8.5.x) y de casos de uso (8.1).

## Actionable Tasks

- [x] **T15** Reducir los 3 diagramas de actividad a un solo flujo legible.
- [x] **T16** Re-renderizar vía `docs/uml/render-simple-uml.sh`.
- [x] **T17** Verificar en `build/simple-report.csv` que los 3 rinden OK.

## Reglas de la ronda 5

- Un solo flujo inicio→fin por diagrama.
- **Máximo 2 carriles** por diagrama. Reducir los cambios de carril
  consecutivos: agrupar las acciones del mismo actor aunque el diagrama
 akey no sea estrictamente estricto.
- **Máximo 2 decisiones** (`if`) por diagrama. Sin cadenas `elseif`.
- `fork...fork again...end fork`: máximo **2 ramas**, y solo si aportan
  paralelismo real. Si hay 3 o más, colapsar a una sola acción.
- **Eliminar todos los `note`**. Si un dato es imprescindible, va dentro
  del texto del nodo.
- Nodos: máximo **15** por diagrama. Cada nodo es una acción, sin listas
  de 3 pasos separados por `;`.
- Las entidades mencionadas dentro del texto de los nodos se conservan
  (`:Cambiar Pedido a PAGADO;` sigue siendo legible).
- Conservar `@startuml`, y `@enduml` final.

## Verification

- `build/simple-report.csv` con las 3 filas en `OK`.
- Ningún diagrama supera 2 carriles, 2 decisiones ni 15 nodos.
- Todo nodo comienza y termina con `:` y la última instrucción es `stop`.

## Decision Log

- **Decisión 2026-10-21**: en `8.3.3` se elimina el bloque final de validación
  de stock en checkout. Es un segundo flujo dentro del mismo diagrama y el
  propio texto lo remite al diagrama de Checkout (8.3.1), que ya lo cubre.
- **Riesgo**: reducir por debajo del flujo real deja de ser un diagrama del
  sistema y pasa a ser un esquema. El corte se limita a nodos redundantes,
  carriles rebotados y notas; ningún actor ni ninguna transición de estado
  esencial desaparece.

## Resultados (2026-10-21)

- **Nodos**: 86 → 39. **Carriles**: 12 → 6. **Decisiones**: 16 → 6.
  **Ramas de fork**: 12 → 4. **Notas**: 5 → 0.
- **Bytes de los 3 `.puml`**: 5727 → 3175 (−45%).
- Render: los 16 `-simple.puml` rinden OK, 0 errores.

| Diagrama | Nodos | Carriles | Decisiones | Fork | Notas |
|----------|-------|----------|------------|------|-------|
| 8.3.1 checkout y pago | 34 → 15 | 4 → 2 | 5 → 2 | 4 → 2 | 1 → 0 |
| 8.3.2 gestión de pedidos | 27 → 15 | 5 → 2 | 3 → 2 | 5 → 2 | 1 → 0 |
| 8.3.3 control de stock | 25 → 9 | 3 → 2 | 7 → 2 | 3 → 0 | 3 → 0 |

Qué se perdió a propósito:

- **8.3.1**: el actor "Pasarela de Pago" desapareció como carril; su acción
  quedó dentro del nodo del sistema. La rama de timeout de 30 s y la de
  método de pago inválido se fusionaron en una sola decisión "¿Pago aprobado?".
- **8.3.2**: el actor "Empresa de envío" y el "Sistema de Email" ya no son
  carriles; ambas acciones quedaron en nodos. La decisión de producción con
  productos personalizados se redujo a una acción del operador.
- **8.3.3**: se eliminó el flujo completo de validación de stock durante el
  checkout (duplicaba a 8.3.1) y las dos notas al pie. Quedó solo la
  modificación de stock, con 2 ramas.

Lo que se conservó: todas las transiciones de estado del pedido
(PENDIENTE_PAGO → PAGADO → ENVIADO → ENTREGADO, EN_PRODUCCION,
PREPARADO_ENVIO), el descuento de stock, el registro de MovimientoInventario y
las dos salidas de error por pago no confirmado y guía inválida.

## Next Step

Sin commitear en `master`. Sigue pendiente decidir rama, commit y el hallazgo de
nombres de columna de 8.2.7.

---

# Ronda 6 — Simplificar los diagramas de secuencia

## Objective

Aplicar a `8.4.1`, `8.4.2` y `8.4.3` la misma reducción de la ronda 5. Son los
diagramas más pesados del set: 9-10 participantes y 40+ mensajes cada uno.

## Problem

Un diagrama de secuencia con 10 lifeline y 45 flechas no se lee en una página A4:
el ojo no puede seguir el flujo. El ruido dominante son los mensajes SQL
consecutivos (`BEGIN`/`INSERT`/`UPDATE`/`COMMIT` = 4 flechas para una sola
operación lógica) y los bloques `par` de 5 ramas.

## Scope

**In scope (3 archivos):** `docs/uml/8.4.{1,2,3}-secuencia-*-simple.puml`

**Out of scope:** los `.puml` originales, clases (8.2.x), actividad (8.3.x),
estados (8.5.x), casos de uso (8.1).

## Actionable Tasks

- [x] **T18** Reducir los 3 diagramas de secuencia.
- [x] **T19** Verificar participantes, mensajes y bloques.
- [x] **T20** Re-renderizar y confirmar OK.

## Reglas de la ronda 6

- **Máximo 5 lifeline** (actor + participants) por diagrama.
- **Máximo 14 mensajes** por diagrama.
- **Mensajes SQL consecutivos sobre la misma operación se funden en uno.**
  Ejemplo: `BEGIN TRANSACTION` + `INSERT pedidos` + `UPDATE carritos` +
  `COMMIT` → un solo mensaje `Persistir pedido y líneas en transacción`.
- `par ... else ... else`: máximo **2 ramas**. Con 3 o más, fundir en un solo
  mensaje que describa el conjunto.
- `alt`: **máximo 1 bloque** por diagrama, con 2 fragmentos. Se elimina el
  anidamiento (`alt` dentro de `alt`).
- Eliminar `note over` / `note left` / `note right`.
- Las cabeceras `== SECCIÓN ==` se conservan **solo si siguen siendo ≤3**. Si
  sobran, fundirlas.
- **La ruta feliz se conserva completa**: ninguna transición esencial desaparece.
- Conservar `@startuml` y `@enduml`.

## Verification

- ≤5 lifeline, ≤14 mensajes, ≤1 `alt`, ≤2 ramas de `par`, 0 `note`.

## Resultados (2026-10-21, commit `2c1a3b71`)

- **Mensajes**: 107 → 47 (−56%). **Lifeline**: 25 → 16.
- `alt`: 3 → 1. `par`: 2 → 0. `note`: 2 → 0.
- **Bytes de los 3 `.puml`**: 7275 → 3729 (−49%).
- Render OK en los 16 `-simple.puml`, cero errores.

| Diagrama | Lifeline | Mensajes | alt | par | note |
|----------|----------|----------|-----|-----|------|
| 8.4.1 registro 2FA | 6 → 5 | 36 → 16 | 2 → 1 | 0 → 0 | 0 → 0 |
| 8.4.2 checkout pasarela | 10 → 6 | 38 → 16 | 1 → 0 | 1 → 0 | 2 → 0 |
| 8.4.3 gestión pedido | 9 → 5 | 33 → 15 | 0 → 0 | 1 → 0 | 0 → 0 |

**Desviación del objetivo, anotada:** la regla decía ≤14 mensajes y quedaron
15-16. No se recortó más porque hacerlo exigía borrar pasos de la arquitectura
que el diagrama existe para documentar: la separación Controller → Service → DB
y el tramo del webhook asíncrono. Se prefiere un diagrama legible con 16
mensajes que uno mutilado con 12.

**Excepción de lifeline:** 8.4.2 quedó con 6 en vez de 5. El checkout necesita
OrderService y PaymentService por separado: fusionarlos ocultaría que el pago
tiene su propio ciclo de vida, independiente del pedido.

Qué se conservó a propósito: el registro y la verificación de correo (8.4.1),
las dos ramas de 2FA —TOTP y OTP por email—, el webhook asíncrono como fuente de
verdad del pago (8.4.2) y la confirmación de entrega como evento posterior
desde la empresa de envío (8.4.3).

---

# Ronda 7 — Recorte de los diagramas de estados

## Objective

Aplicar la regla de "0 notas" a `8.5.1` y `8.5.2`. No hay nada más que
recortar: son 5-7 estados y 7-8 transiciones, ya en el piso.

## Problem

Los dos diagramas de estados son correctos y compactos. El único elemento
redundante son las 3 notas al pie.

## Scope

**In scope (2 archivos):** `docs/uml/8.5.{1,2}-estado-*-simple.puml`

## Actionable Tasks

- [x] **T21** Recortar notas de los 2 diagramas de estados.
- [x] **T22** Re-renderizar y confirmar OK.

## Reglas de la ronda 7

- Eliminar los 3 bloques `note right of ... end note`.
- El contenido útil de cada nota se **funda en la etiqueta de la transición**,
  sin alargarla más de una línea.
- Los `skinparam state` se conservan intactos.

## Excepción documentada

**La nota de `8.5.1` NO se elimina.** No es decorativa: reconcilia el enum real
del código (`OrderStatus`, 4 estados) con los sub-estados académicos que el
documento describe en otra sección (PAGADO, CONFIRMADO, EN_PRODUCCION,
PREPARADO_ENVIO, ENVIADO). Sin esa nota, un lector que conoce la parte académica
concluye que el diagrama está incompleto.

En `8.5.2` sí se eliminan las 2 notas: la de `DESHABILITADO` repite lo que ya
dice su propia transición ("limpieza completada") y la de `ACTIVO` repite el
self-loop "login con código válido". Se conserva la idea con la etiqueta
`desactivación por purga (job batch)`.

## Verification

- `build/simple-report.csv` con las 2 filas en `OK`.
- 1 sola nota en total (la de 8.5.1).

## Next Step

Sin commitear en `master`. Pendientes: commit de las rondas 2-7 y la corrección
de columnas de 8.2.7.
