# Atributos de producto con unidad — EAV extendido

Fecha: 2026-11-03. Confirmado por el usuario: **talla/capacidad como
variante (opción A)** y **atributos en EAV**.

## Objetivo

Que el catálogo soporte los datos variables por tipo de producto que
pidió el usuario: capacidad de la taza en **onzas/libras**, **talla**
de gorra y camisa, y cualquier otro dato extra que traiga cada tipo.
Y que **ningún producto esté obligado a tener galería de imágenes**.

## Qué ya existe (descubierto antes de escribir)

El EAV ya está parcialmente montado en `backend/database/migrations/catalog/`:

| Tabla | Qué tiene | Qué le falta |
|---|---|---|
| `attributes` | `name`, `slug`, `type`, `description`, `is_filterable`, `is_required` | `unit`, `options` (enum), `applies_to` (categorías) |
| `attribute_product` | `product_id`, `attribute_id`, `value` VARCHAR(255) | columnas tipadas (`value_number`, `value_boolean`) |
| `attribute_variant` | `product_variant_id`, `attribute_id`, `value` VARCHAR(255) | columnas tipadas |
| `product_sizes` | `product_id`, `slug`, `label`, `position` | `value` (numérico) + `unit` (oz, lb, ml…) |
| `product_variants` | `finish_*`, `condition`, `stock`, `size_id` FK | — (ya linkea a `product_sizes`) |

## Alcance

1. `attributes` + `unit`, `options` (JSONB), `applies_to` (JSONB).
2. `product_sizes` + `value` (DECIMAL), `unit` (VARCHAR) — acá vive la
   capacidad de la taza (11 oz) y la talla de ropa (M).
3. `attribute_product` + `value_number`, `value_boolean`.
4. `attribute_variant` + `value_number`, `value_boolean`.
5. Tests Feature que fijen el contrato.

Fuera: migraciones de imágenes opcionales (ya es 0..N por schema; lo
pendiente es que la UI lo respete — es trabajo de frontend, no de BD).

## Restricciones

- **Nada de guards** en migraciones (`docs/architecture/migration-pattern.md` §2).
- Migraciones universales por contrato, en `backend/database/migrations/catalog/`.
- Conventional Commits.

## TDD

- **Modo:** activo. **Runner:** `php artisan test --compact` en `backend/`.
- RED → GREEN por migración.

## Tareas

- [x] T1. Tests RED del contrato nuevo (units, options, applies_to,
      valores tipados, size con value+unit). Ruta: `inline`.
- [x] T2. Migración `alter_attributes_add_unit_options_applies_to`.
- [x] T3. Migración `alter_product_sizes_add_value_and_unit`.
- [x] T4. Migraciones `alter_attribute_{product,variant}_add_typed_values`.
- [x] T5. Suite verde + commit por work unit.

## Criterios de aceptación

1. Un atributo puede declarar `unit` ('oz', 'lb', 'ml', 'kg', 'cm'),
   `options` (para `type='select'`) y `applies_to` (categorías).
2. Una talla/capacidad guarda valor numérico + unidad (11 oz = value 11,
   unit 'oz').
3. Los valores EAV soportan número y booleano además de texto.
4. `php artisan test --compact --filter=Attributes` pasa.

## Progreso

- T1-T5 (2026-11-03): 4 migraciones + modelos + tests. Commit `57033241`.
  Ruta: inline (schema ya explorado, patrón de migraciones del repo).

## Evidencia

- `php artisan test --compact --filter=AttributeUnitsTest`: **10 passed**
  (15 assertions). TDD: RED observado (`column "unit" does not exist`,
  `column "value_number" does not exist`) → GREEN.
- `php artisan test --compact tests/Feature/Catalog/{ProductSizeTest,AttributeUnitsTest,ProductExclusiveTest}.php`:
  **18 passed** (26 assertions) — sin regresiones.
- Nota de entorno: los tests del backend necesitan
  `DB_USERNAME=panda-estampados-and-kitsune DB_PASSWORD=devpass`; el
  `.env` del container de Panda apunta por error a la BD de La Voz.
  Se crearon rol + `panda-estampados-and-kitsune_test` en Postgres.

## Próximo paso

Migraciones listas. Pendiente natural: poblar `attributes` /
`product_sizes` con los datos reales de cada tipo de producto (capacidad
de tazas, tallas de ropa) y que el admin los edite.

## Extensión — diccionario sembrado (2026-11-03)

- `AttributeDefinitionSeeder` (commit `7656a7f5`) siembra el vocabulario:
  `capacity` (number, `oz`, drinkware), `size` (select, CH/M/G/XG),
  `material` (select), `weight` (number, `kg`), `microwave_safe` y
  `dishwasher_safe` (boolean, drinkware). Idempotente, enganchado a
  `DatabaseSeeder`.
- Tests: `AttributeDefinitionSeederTest` 7 passed (14 assertions).
  Pool completo catálogo: 21 passed (34 assertions).

## Pendiente

- Poblar `product_sizes` / `attribute_product` con los datos reales de
  las piezas de Panda y Kitsune — eso requiere que esos productos
  existan en la BD (hoy viven como mocks en el frontend; la BD de dev
  está sin migrar).
- Admin para editar atributos y tallas.
