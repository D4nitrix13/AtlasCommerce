# Instalación

Clonar el repo y dejar las dependencias listas en backend y frontend.

## 1. Clonar el repositorio

```bash
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce
```

Estructura del repo:

```txt
AtlasCommerce/
├── backend/          # Laravel API
├── frontend/         # Astro app
├── docker/           # Docker Compose para dev
├── docs/             # Esta documentación
├── database/         # Migraciones + seeds compartidos
└── Caddyfile         # Reverse proxy config
```

## 2. Instalar dependencias del backend

```bash
cd backend
composer install
```

Esto descarga todas las dependencias PHP declaradas en `composer.json`
y genera `vendor/` y `composer.lock`.

### Verificar

```bash
ls -la vendor/laravel/framework/src/Illuminate/
```

## 3. Instalar dependencias del frontend

```bash
cd ../frontend
pnpm install
```

`pnpm install` descarga las dependencias según `pnpm-lock.yaml` (modo
reproducible). Usa `--frozen-lockfile` en CI para garantizar que el
lockfile no cambie.

### Verificar

```bash
ls -la node_modules/astro/
```

## 4. Configurar variables de entorno

Backend:

```bash
cd ../backend
cp .env.example .env
php artisan key:generate
```

Frontend:

```bash
cd ../frontend
cp .env.example .env
```

## 5. Verificar instalación

```bash
# Backend: chequea que PHPUnit carga
cd backend && composer test --list-tests

# Frontend: chequea que Astro CLI responde
cd ../frontend && pnpm run astro --version
```

## Troubleshooting

### `composer install` lento

Limpia caché:

```bash
composer clear-cache
composer install --no-cache
```

### `pnpm install` falla con peer dependencies

```bash
rm -rf node_modules
pnpm install --no-frozen-lockfile
```

### Conflictos de versión PHP

Si tenés varias versiones de PHP instaladas, usá `phpenv` o `update-alternatives`:

```bash
sudo update-alternatives --set php /usr/bin/php8.3
```

### Conflictos de versión Node

Con `nvm`:

```bash
nvm install 22.12
nvm use 22.12
nvm alias default 22.12
```

## Próximo paso

[databases.md](databases.md "databases.md") — Levantar Postgres y
Redis con `Docker Compose`.
