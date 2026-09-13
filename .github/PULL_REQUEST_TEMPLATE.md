<!-- markdownlint-disable MD013 MD033 MD041 -->

<!--
Plantilla de Pull Request para este monorepo.

Convención:
- Todos los PRs en español (Conventional Commits español).
- scopes: backend, frontend, docs, infra, ci.
- Review budget: 400 líneas (size:exception si se excede).
- Strict TDD: test primero → fail → fix → pass.
-->

## Qué cambia

<!-- Descripción breve y concreta de los cambios -->

## Por qué

<!-- Motivación: bug fix, feature, refactor, etc. -->
<!-- Si cierra un issue, referenciá con `Closes #X` o `Refs #X` -->

## Cómo se prueba

<!-- Comandos para verificar (tests, smoke test, manual QA) -->

```bash
# Tests específicos de este PR
docker exec <container-name> php artisan test --compact --filter='...'

# Suite completa
docker exec <container-name> php artisan test --compact
```

<!-- Tests nuevos o actualizados (count + paths) -->

## Checklist

- [ ] Conventional commits en español (`tipo(scope): descripción`)
- [ ] Tests agregados o actualizados (happy + failure paths)
- [ ] `vendor/bin/pint --dirty --format agent` clean
- [ ] `composer analyse` sin nuevos errors (los pre-existing en master NO son blocker)
- [ ] Si hay migration: `up`/`down` reversibles verificados
- [ ] Si hay doc: español, sigue `documentation-styleguide`
- [ ] Si cambia API: actualicé el contrato / recursos
- [ ] Conventional commits en español (10 commits o similar, no monolito)
- [ ] Si toca frontend: a11y + i18n revisados
- [ ] Si toca multi-tenant: store_id filter verificado

## ⚠️ Size exception (si > 400 líneas)

<!-- Justificación de por qué este PR excede el budget de 400 líneas -->

- **Cambios**: X files, +Y / -Z líneas (~W total)
- **Excedido**: V líneas (WW%)
- **Razón**: descripción breve

## Relacionado

<!-- Links a specs, designs, reviews, engram observations -->

- Review: ...
- Proposal: ...
- Design: ...
- Tasks: ...
- Apply progress: ...
