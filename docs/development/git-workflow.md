# Git Workflow — Commits, branches, PRs

Convenciones para el versionado del proyecto.

## Convenciones de commits

Conventional Commits en español con scope obligatorio.

```txt
tipo(scope): descripción corta

[cuerpo opcional]

[footer opcional]
```

### Tipos

| Tipo       | Para                                  |
| ---------- | ------------------------------------- |
| `feat`     | Nueva funcionalidad                  |
| `fix`      | Bug fix                              |
| `docs`     | Cambios solo en documentación        |
| `style`    | Formateo sin cambio lógico           |
| `refactor` | Refactor sin cambio de comportamiento |
| `test`     | Agregar o arreglar tests              |
| `chore`    | Mantenimiento (deps, build, etc.)    |
| `ci`       | Cambios en CI/CD                      |

### Scopes

| Scope          | Cubre                                           |
| -------------- | ----------------------------------------------- |
| `backend`      | Cambios en `backend/`                           |
| `frontend`     | Cambios en `frontend/`                          |
| `docs`         | Cambios en `docs/`                              |
| `infra`        | Cambios en `docker/`, `infra/` (Caddy, K8s)    |
| `ci`           | Cambios en `.github/workflows/`                 |
| `*` (sin scope) | Cambios globales (raíz, configuración)          |

### Ejemplos

```txt
feat(backend): endpoint /api/auth/login con Sanctum tokens

- Implementa AuthController.login con validación via FormRequest
- Token Sanctum con abilities y device_name
- Tests E2E con happy path + credenciales inválidas

fix(frontend): redirect post-login mantiene subdominio admin

Antes: window.location.href = '/es/admin/dashboard' (forzaba host)
Ahora: window.location.href = window.location.origin + '/dashboard'

docs: guía de setup para development local

- Cubre instalación de PHP, Node, Docker
- Configuración de /etc/hosts para multi-tienda
- Caddyfile con routing por host
```

## Branches

Formato: `tipo/scope-descripcion-corta`.

```bash
feat/auth-login-frontend
fix/cart-overflow-mobile
refactor/backend-payments-service
docs/multi-store-decisions
```

Nunca pushees directo a `master`. Todo cambio va por PR.

## Pull Requests

### Título

Mismo formato que los commits:

```txt
feat(backend): endpoint /api/auth/login con Sanctum tokens
```

### Descripción

```md
## Qué cambia

- Resumen del cambio en 1-2 frases.

## Por qué

- Problema que resuelve.
- Link al issue/ticket (si hay).

## Cómo se prueba

- Pasos para reproducir.
- Tests agregados/modificados.

## Screenshots

- Si es UI.

## Checklist

- [ ] Tests pasan localmente
- [ ] Linter pasa
- [ ] Docs actualizadas (si aplica)
- [ ] Sin secrets commiteados
```

### Reviewers

- Backend: 1 reviewer con conocimiento de Laravel.
- Frontend: 1 reviewer con conocimiento de Astro/React.
- Cambios grandes: 2 reviewers.

### Merge

- Squash cuando el PR tiene commits chicos.
- Rebase cuando los commits son limpios.
- Nunca force-push a `master` durante un PR abierto.

## Versionado

El proyecto no usa `git tag` para versiones todavía. Roadmap en
[api/backend/TASKS.md](../../backend/TASKS.md "api/backend/TASKS.md").

## TL;DR

```bash
# 1. Crear branch
git switch -c feat/<descripcion>

# 2. Hacer cambios + tests
# ...

# 3. Linter local
composer precommit   # backend
pnpm run typecheck && pnpm run lint   # frontend

# 4. Commit con conventional
git add -p
git commit -m "feat(backend): descripcion"

# 5. Push + PR
git push origin feat/<descripcion>
gh pr create --title "feat(backend): ..." --body "..."
```

## Próximo paso

[architecture/](../architecture/overview.md "architecture/overview.md") — Visión
general del sistema.
