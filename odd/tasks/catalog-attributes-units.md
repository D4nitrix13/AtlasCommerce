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

- [ ] T1. Tests RED del contrato nuevo (units, options, applies_to,
      valores tipados, size con value+unit). Ruta: `inline`.
- [ ] T2. Migración `alter_attributes_add_unit_options_applies_to`.
- [ ] T3. Migración `alter_product_sizes_add_value_and_unit`.
- [ ] T4. Migraciones `alter_attribute_{product,variant}_add_typed_values`.
- [ ] T5. Suite verde + commit por work unit.

## Criterios de aceptación

1. Un atributo puede declarar `unit` ('oz', 'lb', 'ml', 'kg', 'cm'),
   `options` (para `type='select'`) y `applies_to` (categorías).
2. Una talla/capacidad guarda valor numérico + unidad (11 oz = value 11,
   unit 'oz').
3. Los valores EAV soportan número y booleano además de texto.
4. `php artisan test --compact --filter=Attributes` pasa.

## Progreso

(nada aún)

## Evidencia

(nada aún)

## Próximo paso

T1 — tests RED.
