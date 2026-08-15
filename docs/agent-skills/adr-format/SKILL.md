---
name: adr-format
description: ADR (Architecture Decision Record) format: title, context, alternatives, decision, consequences. Trigger: adding a new significant architectural decision to docs/architecture/decisions.md.
---

# ADR Format

Source: `docs/architecture/decisions.md` + `docs/DOCUMENTATION_STYLEGUIDE.md`.

## What is an ADR

Architecture Decision Record - record of a **significant** architectural
decision. Each entry documents:

1. What was decided.
2. Why.
3. What alternatives were considered.
4. What consequences it has.

## When to create an ADR

✅ **YES** create ADR when:

- Choosing a significant technology / framework / library.
- Changing the system architecture (monolith → events, etc.).
- Adopting a new pattern (MVC variant, CQRS, etc.).
- Changing project conventions (named args, strict types, etc.).
- Deciding between shared DB vs DB-per-tenant.
- Choosing a new stack (Caddy vs Nginx, Redis vs Memcached).

❌ **DO NOT** create ADR for:

- Trivial decisions (what color to use).
- Reversible decisions without cost.
- Specific implementation details.
- Refactors that don't change architecture.

## Location

`docs/architecture/decisions.md` - append at the end.

## Format

```md
## ADR-XXX: Short and descriptive title

**Fecha:** YYYY-MM
**Estado:** Aceptada | Pendiente | Rechazada | Superseded by ADR-YYY

### ADR-XXX - Contexto

What problem are we solving. Why now.

### ADR-XXX - Alternativas consideradas

1. **Option A** - brief description.
 - Pro: ...
 - Contra: ...
2. **Option B** - brief description.
 - Pro: ...
 - Contra: ...
3. **Option C** - the chosen one (optional mark here).

### ADR-XXX - Decisión

What we chose and why. One single decision per ADR.

### ADR-XXX - Consecuencias

- **Positivas:** what we gain.
- **Negativas:** what we lose or risk.
- **Mitigación:** how we reduce the risk.
```

## Real example (ADR-001 from the project)

```md
## ADR-001: Una sola base de datos para ambas tiendas

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-001 - Contexto

Panda Estampados y Kitsune son dos tiendas con identidad separada pero
operación compartida. Se necesita decidir si cada tienda tiene su propia
base de datos o si comparten una sola instancia.

### ADR-001 - Alternativas consideradas

1. **Database per tenant** - Una instancia `PostgreSQL` por tienda.
2. **Schema per tenant** - Un esquema `PostgreSQL` por tienda en la misma
 instancia.
3. **Shared database con `store_id`** - Una sola instancia, tablas con
 campo `store_id`.

### ADR-001 - Decisión

Shared database con `store_id` en las tablas transaccionales.

### ADR-001 - Consecuencias

- **Positivas:** Una sola instancia que mantener, respaldar y escalar.
 Consultas cross-store simples. Inventario compartido natural.
- **Negativas:** Riesgo de filtrar datos entre tiendas si el `store_id`
 no se aplica correctamente. Las migraciones afectan a todas las tiendas.
- **Mitigación:** Middleware que inyecta `store_id` automáticamente.
 Policies que validan acceso por tienda.
```

## Numbering

- **Sequential**: `ADR-001`, `ADR-002`, ... `ADR-099`.
- Find the next available number in `decisions.md`.
- **DO NOT reuse** numbers, even if rejected.

## Title naming

- Imperative or short noun phrase.
- In Spanish.
- Googleable.

**Good:**

```txt
ADR-005: Inventario compartido inicialmente
ADR-007: Sanctum Bearer tokens para autenticación
```

**Bad:**

```txt
ADR-005: Decisión sobre stock
ADR-007: Auth
```

## States

| Estado | When |
|---|---|
| `Aceptada` | Decision in force, applied to code. |
| `Pendiente` | Decided but not implemented. |
| `Rechazada` | Considered and discarded. Document to avoid repeating. |
| `Superseded by ADR-YYY` | Replaced by a more recent one. |

When superseded, **DO NOT** delete the old one. Add `Superseded by ADR-YYY`.

## Writing rules

- 80 chars per line (see `documentation-styleguide`).
- Folder structures in `txt` block.
- Commands in `bash` block.
- Env vars in `env` block.
- Headings always with the ADR identifier (avoids `MD024`):

```md
### ADR-001 - Contexto
### ADR-001 - Decisión
```

## Ready-to-use template

```md
## ADR-XXX: Title

**Fecha:** $(date +%Y-%m)
**Estado:** Aceptada

### ADR-XXX - Contexto

[What problem are we solving? Why now?]

### ADR-XXX - Alternativas consideradas

1. **[Option A]** - description.
2. **[Option B]** - description.
3. **[Option C]** - description.

### ADR-XXX - Decisión

[What we chose and why.]

### ADR-XXX - Consecuencias

- **Positivas:** ...
- **Negativas:** ...
- **Mitigación:** ...
```

## Trigger patterns

Activate when:

- User asks "let's make an ADR for X".
- New significant architectural decision.
- Stack / framework / pattern change.
- Decision affecting multiple modules.

## Anti-patterns

- ❌ ADR without alternatives (there's always at least 2).
- ❌ ADR without consequences (no trade-off analysis).
- ❌ ADR with detailed implementation (that goes in commits).
- ❌ ADR of trivial decisions.
- ❌ Re-numbering ADRs (breaks history).
- ❌ Deleting old ADRs (keep the record).
- ❌ Headings without identifier (`### Contexto` instead of `### ADR-001 - Contexto`).

## Related skills

- `documentation-styleguide` - general markdown format.
- `conventional-commits-and-prs` - ADR commit.
