# Reconciliación del auditor de galería

## Objetivo

Que `assets:audit-gallery` deje de mentir sobre `product_images`, y que las
divergencias reales que reporte se puedan reparar sin riesgo.

## Problema

El auditor reportaba **7.152 filas faltantes** sobre un `product_images` que
tiene 9.836 filas. El conteo no cuadraba y bloqueaba el `--fix`, que es
irreversible.

## Por qué

La causa raíz era un off-by-one: `GalleryRoleAuditor::productSlugFor()` sacaba
el slug del producto del path con un offset fijo (`count($parts) - 3`). Ese
offset sólo acierta en el layout multi-variante
(`{brand}/{product}/{variant}/{file}`); en los productos de una sola variante
(`{brand}/{product}/{file}`) cae en la **marca**. Como
`StorageKeyDeriver::derive()` mete el entity dentro de la clave
(`tenants/{tenant}/products/{entity}/{hash}.webp`) y el seeder siempre usa
`$product->slug`, el auditor inventaba claves que no pueden existir.

**316 de 356 productos** son single-variant: 7.204 de 10.249 archivos.

La hipótesis previa en el repo ("colisión de sha-256 entre archivos idénticos")
era **incorrecta**: la key incluye el slug del producto, así que no colisiona
entre productos. El colapso real es legítimo y buscado — content-addressed
storage deduplica el archivo, y la fila se separa por variante.

## Alcance autorizado

1. Arreglar la derivación del `entity` en el auditor.
2. Corregir el ruido de `variants_without_primary`.
3. Dejar de descartar en silencio los directorios sin producto en BD.
4. Aterrizar el diff de `CatalogAssetsSeeder` (`variant_id` en la clave).
5. Corregir el test que codifica la hipótesis vieja.
6. Correr `--fix` una vez la reconciliación cierre.

## Fuera de alcance

- Importar los 10 productos que están en disco pero no en `products`. Es
  decisión de negocio, no de código. El auditor sólo los **reporta**.
- Renombrar assets mal nombrados en origen (PENDIENTE #5 de la sesión previa).
- Los 54.358 errores de lint (PENDIENTE #6, diferido explícitamente).

## Tareas

- [x] **T1** — `entity` desde `variantFolders()`, no desde el path
- [x] **T2** — test de regresión del auditor (no tenía ninguno)
- [x] **T3** — `variants_without_primary` fuera del loop por storage key
- [x] **T4** — directorios sin producto en BD: reportar, no descartar
- [x] **T5** — `CatalogAssetsSeeder`: `variant_id` en la clave del `updateOrCreate`
- [x] **T6** — `SeedCatalogAssetsPersistenceTest`: la aserción de la 270 codifica la hipótesis vieja
- [x] **T7** — `--fix` sobre la reconciliación verificada
- [x] **T8** — suite del área + cierre

## Restricciones

- **El `--fix` corre sólo después de T1-T6.** Con la reconciliación abierta,
  insertaba ~6.972 filas apuntando a `products/{marca}/{hash}.webp`, rutas que
  nunca se subida (verificado: 0 directorios de marca en el storage).
- Los tests corren **siempre** contra `instrumentos-musicales-la-voz_test`. El
  guard de `tests/bootstrap.php` aborta si no.
- `php -l` no detecta named arguments inválidos. Después de editar PHP, correr
  el test — no confiar en el linter.

## Criterios de aceptación

- El dry-run reporta **128** filas faltantes, no 7.152.
- Los 128 son bindings variante→clave de binarios **ya existentes**, no
  archivos nuevos.
- Un test que falle si el bug del `entity` vuelve.
- `product_images` sigue siendo espejo 1:1 del storage.

## Verificación

- Dry-run: 7.152 → **128** (mismos 5 productos que la reconciliación previa).
- Test de regresión: 4/4 verdes; **2 fallan** si se revierte el fix.
- BD dev intacta: 9.836 / 346 / 411. Storage: 9.836 webp.
- `--fix`: **128 insertadas, 0 omitidas**.
- `product_images`: 9.836 → **9.964**.
- Las **9.964** URLs existen en disco. **0 imágenes rotas.**
- Auditor post-fix: `✅ la BD es un espejo fiel del árbol` · `✅ ninguna` sin primary.
- Suite del área: **194 tests, 522 aserciones, 0 fallos.**

## Resultado

El `--fix` no creó archivos: las 128 filas son bindings variante→clave de
binarios que ya estaban. `StorageKeyDeriver` deduplica por contenido a propósito
—los left-handed reusan las fotos del diestro y no hay que subir dos veces el
mismo archivo—, así que dos variantes que comparten foto **deben** apuntar a la
misma key. Lo que las separa es `variant_id`, y eso es lo que fija T5.

Rollback: `pg_dump` de `product_images` previo en `/tmp/opencode/`.

## Pendientes que siguen abiertos

- **10 directorios sin producto en BD** (228 archivos). El auditor ya los
  reporta. Importarlos o descartarlos es decisión de negocio: no hay código que
  los cierre.
- **8 variantes sin primary**, todas de productos con **cero** filas y sin
  directorio en el árbol. Son fotos que nunca se entregaron.
- **57 archivos unparseable** en 8 productos: requieren renombrar en origen.
- PENDIENTE #5 (assets mal nombrados en origen) y #6 (54.358 lint) de la sesión
  previa, sin tocar.

## Próximo paso

Commitear. Todo commiteado en un work unit.