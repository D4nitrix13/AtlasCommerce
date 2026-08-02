# Development — Workflow diario

Comandos y convenciones para el día a día de desarrollo.

## Servicios que se levantan en paralelo

En una sesión de desarrollo típica necesitás 4 servicios:

```bash
# Terminal 1 — Postgres + Redis (Docker)
cd docker/dev && docker compose up

# Terminal 2 — Backend Laravel
cd backend && php artisan serve

# Terminal 3 — Frontend Astro
cd frontend && pnpm run dev

# Terminal 4 — Caddy (solo si necesitás multi-tienda)
sudo caddy run --config /etc/caddy/Caddyfile
```

O usando el script `composer dev` que combina queue + logs + server:

```bash
cd backend
composer dev
```

## Archivos de detalle

| Archivo                                              | Contenido                          |
| ---------------------------------------------------- | ---------------------------------- |
| [backend.md](backend.md "backend.md")                | Comandos Laravel + tests + linter |
| [frontend.md](frontend.md "frontend.md")              | Comandos Astro + dev server       |
| [testing.md](testing.md "testing.md")                 | PHPUnit + Playwright               |
| [linting.md](linting.md "linting.md")                 | Pint + PHPStan + ESLint + Prettier |
| [git-workflow.md](git-workflow.md "git-workflow.md")    | Commits + branches + PRs          |

## Convenciones

- **Commits**: Conventional Commits con scope (`feat(backend): ...`).
- **Branches**: `tipo/scope-descripcion` (ej: `feat/auth-login`).
- **Tests**: Toda nueva feature trae tests.
- **Linter**: `composer precommit` + `pnpm lint` antes de commitear.
- **Doc strings**: PHPDoc en backend, JSDoc en funciones públicas de frontend.

## Multi-tienda en dev

El middleware Astro detecta el tenant por `Host`. Ver
[getting-started/local-domains.md](../getting-started/local-domains.md "getting-started/local-domains.md").

URLs de trabajo:

- `http://pandas-estampados.com` → storefront Panda.
- `http://admin.pandas-estampados.com/login` → admin Panda.
- `http://kitsune.com` → storefront Kitsune.
- `http://admin.kitsune.com/login` → admin Kitsune.

## Debugging

### Backend

```bash
# Tinker (REPL con todas las clases cargadas)
php artisan tinker

# Ver logs
tail -f backend/storage/logs/laravel.log

# Modo debug en navegador
# APP_DEBUG=true en .env
```

### Frontend

```bash
# Astro dev toolbar (esquina inferior)
# Inspecciona props, eventos, y estado del componente

# Ver logs de SSR
tail -f /tmp/frontend.log
```

### Caddy

```bash
sudo journalctl -u caddy -n 100 -f
```

## Tareas comunes

### Agregar un nuevo endpoint

1. Crear `FormRequest` en `app/Http/Requests/`.
2. Crear método en `app/Http/Controllers/Api/`.
3. Crear `API Resource` para la respuesta.
4. Agregar test en `tests/Feature/`.
5. Agregar ruta en `routes/api.php`.
6. Documentar en `docs/backend/api/routes.md`.

### Agregar un componente frontend

1. Crear archivo en `src/components/{ui,public,admin,ecommerce}/`.
2. Si es `React`, agregar prop `interface Props`.
3. Si tiene datos del backend, usar `apiFetch` de `src/lib/api.ts`.
4. Agregar story o test manual si aplica.
5. Documentar si es reutilizable.

### Crear migración

```bash
cd backend
php artisan make:migration create_<table>_table
```

Editar el archivo generado y luego:

```bash
php artisan migrate
```

## Próximo paso

[backend.md](backend.md "backend.md") — Comandos específicos del
backend.
