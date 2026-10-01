# Maintenance Checklist — Archivos ignorados y limpieza

> Lista de archivos/carpetas que el repo ignora o ya no usa.
> Verificá esta lista antes de hacer `git add .` para evitar commits accidentales.

## Archivos purgados localmente (no tracked, ignorar)

| Path | Razón de purga | Patrón en `.gitignore` |
| --- | --- | --- |
| `auth-design-system.zip` | ZIP de páginas HTML de un sistema de auth anterior (ago 2026). Reemplazado por `frontend/src/pages/[locale]/account/`. NO usar como referencia. | `*.zip` |
| `.astro/dev.log` | Log de dev server de Astro. Regenerado en cada `astro dev`. | `*.log` |
| `composer.json` (root) | Vacío (`{}`). Composer real vive en `backend/composer.json`. El root composer.json era un placeholder histórico. | `/composer.json` |

## Archivos cubiertos por `.gitignore` (no tocar)

| Path / Patrón | Por qué se ignora |
| --- | --- |
| `.venv/`, `venv/`, `__pycache__/`, `*.py[cod]`, `*$py.class` | Doorstop tooling bajo `docs/requirements/` (legacy). |
| `docs/requirements/docs/` | Output HTML de `doorstop publish all`, regenerado. |
| `.atl/`, `**/.atl/` | Cache del skill-registry (regenerado por sesión). |
| `openspec/specs/cache/` | SDD tooling. |
| `.playwright-cli/`, `.codegraph/` | AI tooling artifacts. |
| `backend/backend/coverage/`, `backend/backend/` | Symlink roto de coverage. |
| `.env`, `.phpunit.result.cache`, `*.bak`, `*.erm` | Env local y archivos de editor. |

## Procedimiento

1. **Antes de commitear**: `git status --short` y verificar que solo aparecen archivos intencionales.
2. **Si aparece un archivo de esta lista**: NO lo agregues con `git add -f`. Es un artifact local que debe quedar ignorado.
3. **Si necesitas agregar algo legítimo** (ej. un `.zip` de release): usar un path explícito, no broad patterns.
4. **Cleanup manual**: si un archivo local crece (ej. `.astro/dev.log` > 10MB), borrarlo manualmente. No se commitea.

## Auditoría periódica

Cada release, correr:

```bash
# Ver qué está siendo ignorado y considerar purga de working tree
git clean -ndX

# Verificar que los .gitignore patterns siguen vigentes
git check-ignore -v $(find . -maxdepth 2 -not -path './.git*' -not -path './node_modules*' -not -path './backend/vendor*' -not -path './backend/node_modules*' -not -path './frontend/node_modules*' -not -path './.venv*' 2>/dev/null | head -50)
```

Ver también:

- `docs/maintenance/cleanup-checklist.md` (este archivo)
- `scripts/README.md` para limpieza de scripts legacy
- `docs/requirements/README.md` para tooling de requirements
