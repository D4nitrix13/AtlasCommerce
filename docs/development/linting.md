# Linting — Pint, PHPStan, ESLint, Prettier, markdownlint

Pipeline de calidad automatizada. Todo cambio debe pasar por estos
tools antes de mergear.

## Backend — Pint + PHPStan

### Pint (formatter)

`Pint` es un wrapper de `PHP-CS-Fixer` con preset `Laravel`.

```bash
cd backend

# Formatear
composer format

# Solo verificar (no toca archivos)
composer lint

# Solo archivos modificados
composer lint:dirty
```

Config en `backend/pint.json`:

- Preset `Laravel`.
- `declare_strict_types` obligatorio.
- `no_unused_imports`.
- Type hints + ordered imports.
- Trailing commas en multiline.

### PHPStan (análisis estático)

```bash
cd backend
composer analyse
```

Config en `backend/phpstan.neon`:

- Level 7 (estricto).
- Paths: `app/`, `routes/`, `database/`, `config/`.
- Excluye: `database/migrations/`, `database/factories/`,
  `database/seeders/`.

## Frontend — ESLint + Prettier

### ESLint

`ESLint` 9 con flat config.

```bash
cd frontend

# Revisar
pnpm run lint

# Arreglar
pnpm run lint:fix
```

Config en `frontend/eslint.config.js`:

- `@typescript-eslint/recommended`.
- React + React Hooks.
- JSX A11y.
- Prettier compatibility.

Reglas custom:

- `max-lines-per-function`: 40.
- `max-depth`: 3.
- `max-params`: 5.
- `@typescript-eslint/no-explicit-any`: error.

### Prettier

```bash
cd frontend

# Formatear
pnpm run format

# Solo verificar (modo CI)
pnpm run format:check
```

Config en `frontend/.prettierrc.json`:

- `singleQuote: true`.
- `semi: true`.
- `trailingComma: es5`.
- `printWidth: 100`.
- Plugins: `astro`, `prettier-plugin-astro`.

## Docs — markdownlint

```bash
# Lint todos los archivos
markdownlint 'docs/**/*.md' --ignore node_modules

# Lint un archivo
markdownlint docs/README.md

# Auto-fix donde sea posible
markdownlint --fix docs/
```

Config en `.markdownlint.json`:

- `MD013` line-length 80 (excepto code blocks, tables, headings).
- `MD024` siblings_only (no duplicar headings en el mismo nivel).
- `MD033` permite `br`, `kbd`, `sub`, `sup`.
- `MD041` first line must be H1.
- `MD046` code blocks deben ser fenced.

Reglas de estilo en
[docs/DOCUMENTATION_STYLEGUIDE.md](../DOCUMENTATION_STYLEGUIDE.md "docs/DOCUMENTATION_STYLEGUIDE.md").

## Pre-commit local

Antes de commitear:

```bash
# Backend
cd backend
composer precommit   # = lint + analyse + test

# Frontend
cd ../frontend
pnpm run typecheck && pnpm run lint && pnpm run format:check

# Docs
cd ..
markdownlint 'docs/**/*.md' --ignore node_modules
```

## CI

El pipeline en
`.github/workflows/quality-gate.yml` corre todo automáticamente en:

- Push a `master` o `main`.
- Pull requests.

Si algo falla, el PR no se puede mergear.

## Próximo archivo

[git-workflow.md](git-workflow.md "git-workflow.md") — Commits y PRs.
