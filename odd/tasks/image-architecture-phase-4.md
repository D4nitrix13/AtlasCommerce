<!-- markdownlint-disable MD013 -->

# Plan: Image Architecture — Fase 4 (Migration pattern)

> **Status**: en ejecución. Branch base: `master` HEAD `6cb7fd74` (Fases 2-3 mergeadas).
>
> **Scope**: formalizar el patrón de migraciones del proyecto en `docs/architecture/migration-pattern.md`. NO refactoriza migraciones históricas (riesgoso en tenants deployados). NO agrega migraciones nuevas.
>
> **Engram mirror**: `odd/image-architecture-phase-4/tasks`

---

## 0. Contexto

El audit READ-ONLY de Fase 2 confirmó el default aprobado:

> Migrations: NO conditional, todas universales; para fields no-universales usar tabla `media.custom_properties` (JSON) o `metadata` separada

Pero `grep -rln 'Schema::has' backend/database/migrations/` arrojó 3 migraciones con condicionales. Revisadas una por una:

| Migration | Guard | Veredicto |
|---|---|---|
| `auth/2026_08_17_000002_add_last_login_at_to_users.php` | `if (! Schema::hasColumn('users', 'last_login_at'))` antes de `addColumn` | **Justificado** — docblock documenta: defensa contra baseline incompleto en installs legacy. Idempotente. |
| `shipping/2026_07_29_000001_convert_shipping_zones_base_cost_to_cents.php` | `if (Schema::hasColumn('shipping_zones', 'base_cost'))` antes de migrar valores | **Anti-pattern** — el guard está en el estado VIEJO, no en el NUEVO. Re-corrida falla porque `base_cost_cents` se agrega sin guard. No idempotente. |
| `catalog/2026_06_22_032014_create_category_product_table.php` | `if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id'))` antes del backfill | **Justificado** — backfill defensivo contra half-applied migrations. Tabla nueva + backfill opcional. |

Total: 2 justificadas + 1 anti-pattern. El refactor de la #2 se difiere a Fase 6 cleanup (tocar migrations aplicadas en producción es destructivo).

---

## 1. Archivos a tocar

| Archivo | Acción |
|---|---|
| `docs/architecture/migration-pattern.md` | **CREAR** — formaliza el patrón con decisión tree + los 3 ejemplos reales |
| `odd/tasks/image-architecture-phase-4.md` | **CREAR** — este task file |

Sin tocar: migraciones existentes, código, modelos.

---

## T1 — Crear `docs/architecture/migration-pattern.md`

**Estructura del doc**:

1. §1 Principio rector — todas las migraciones son universales; cada tenant corre las mismas migraciones con su propio seed.
2. §2 Default — `NO Schema::hasColumn` ni `if (Schema::hasTable(...))` en migrations. Si la lógica es condicional, vive en código de runtime (Factory, Seeder, Repository), NO en migration.
3. §3 Excepciones legítimas — guardas defensivas con justificación explícita en docblock:
   - Backfill que depende de datos existentes en otra tabla (ej. `category_product_table.php`).
   - Defensa contra baseline incompleto en installs legacy (ej. `add_last_login_at_to_users.php`).
   - Cada excepción debe llevar docblock de ≥3 líneas explicando el "por qué" + link al ticket/decision SDD si existe.
4. §4 Anti-patterns — con el ejemplo real de `convert_shipping_zones_base_cost_to_cents.php` y la fix correcta (guard en el estado NUEVO, no el VIEJO).
5. §5 Datos tenant-specific — para features no universales:
   - Opción A: columna JSON `custom_properties` (ej. `media.custom_properties` ya existe, dead code hoy).
   - Opción B: tabla de extension `tenant_X_metadata` con key/value.
   - Opción C: tabla por feature en `database/migrations/<area>/`.
   - Decisión tree: preferir A para valores chicos (<10KB total), B para configuraciones grandes, C para entidades nuevas universales.
6. §6 Refactors peligrosos — lista de migraciones que NO se refactorizan sin coordinación: las 3 auditadas están en producción. Cualquier cambio requiere `down()` reversible o procedimiento de zero-downtime (shadow column + cutover).
7. §7 Referencias — `assets-naming-convention.md` §6 (Fase 2 phased rollout), `docs/requirements/TASKS.md` si existe, link al issue/ticket del refactor de #2.

**Estilo**: prosa técnica, ejemplos reales del repo (las 3 migraciones encontradas), sin opinions no fundadas. Markdown permitido en docs (regla "no markdown" aplica solo a commit bodies).

**Commit**: `docs: formalizar patrón de migraciones (universal + JSON metadata + decision tree)`

**Verificación**:

- `markdownlint docs/architecture/migration-pattern.md` → exit 0
- `git diff --stat docs/architecture/migration-pattern.md` → muestra el nuevo archivo

---

## 2. Plan de ejecución

1. **T1** — escribir el doc.
2. Verificar con markdownlint.
3. Commit task file + doc juntos (mismo precedent que Fases 2-3).
4. Reporte breve.

Total esperado: 1 commit, ~200-300 líneas (task file + doc).

---

## 3. Riesgos

| Risk | Mitigation |
|---|---|
| Doc se vuelve opinion piece sin ejemplos concretos | Los 3 ejemplos auditados anclan el doc a realidad del repo |
| Usuarios futuros interpretan "excepciones legítimas" como blanket permission | Docblock obligatorio + revisión explícita (sección §3) |
| Markdownlint falla por line-length | Aplicar configuración `.markdownlint.json` (MD013 exceptúa code/tables/headings) |
| Migración #2 queda sin refactorizar y crea technical debt | Listada en §6 + ticket referenciado en follow-ups |

---

## 4. Follow-ups deferred

- **Refactor de `convert_shipping_zones_base_cost_to_cents.php`** — Fase 6 cleanup (requiere coordination del seguro de rollback).
- **Adoptar `media.custom_properties` como patrón** — requiere modelo + repo de `media` (hoy dead code per audit Fase 2). Ticket: revisar si Spatie MediaLibrary vale la pena o si implementar wrapper propio.
- **Audit completo de `backend/database/migrations/`** — Fase 4 cubre 3 con `Schema::has*`. Quedan 87 migrations por revisar para `DB::statement` condicionales, `try/catch`, etc. Posible ticket para Fase 6+.
