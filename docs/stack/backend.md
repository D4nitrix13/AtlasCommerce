# Backend — Laravel 13 + PHP 8.3

## Visión general

El backend es una `API` REST modular construida con `Laravel 13`. Autentica
peticiones con `Laravel Sanctum` (Bearer tokens) y expone todos los
recursos del sistema. Una sola `API` sirve a ambas tiendas.

## Versiones

| Componente            | Versión  |
| --------------------- | -------- |
| `PHP`                 | 8.3+     |
| `Laravel`             | 13.x     |
| `Laravel Sanctum`     | 4.x      |
| `Laravel Pint`        | 1.x      |
| `Larastan` (PHPStan) | 3.x      |
| `PHPUnit`             | 12.x     |
| `Spatie MediaLibrary` | 11.x     |

## Instalación

### 1. Instalar `PHP` 8.3+

```bash
# Ubuntu / Debian
sudo apt install php8.3 php8.3-cli php8.3-mbstring php8.3-xml \
                 php8.3-pgsql php8.3-zip php8.3-curl php8.3-intl

# macOS (via Homebrew)
brew install php@8.3
brew link --force php@8.3
```

### 2. Instalar `Composer`

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Instalar dependencias del proyecto

```bash
cd backend
composer install
```

`composer.json` declara las dependencias:

```json
{
  "require": {
    "php": "^8.3",
    "laravel/framework": "^13.8",
    "laravel/sanctum": "^4.0",
    "laravel/tinker": "^3.0",
    "spatie/laravel-medialibrary": "^11.23"
  },
  "require-dev": {
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.29",
    "phpunit/phpunit": "^12.5.12"
  }
}
```

## Estructura del proyecto

```txt
backend/
├── app/
│   ├── Console/           # Comandos artisan
│   ├── Enums/             # Enums de estado (ShipmentStatus, etc.)
│   ├── Exceptions/        # Excepciones de dominio
│   ├── Http/
│   │   ├── Controllers/Api/  # Controllers agrupados por dominio
│   │   ├── Middleware/       # Auth, store.domain, CheckPermission
│   │   ├── Requests/         # Form Requests con validación
│   │   └── Resources/        # API Resources para JSON
│   ├── Models/             # Eloquent models
│   ├── Notifications/      # Email + broadcast
│   ├── Policies/           # Authorization policies
│   ├── Providers/          # Service providers
│   ├── Services/           # Lógica de negocio (PaymentService, etc.)
│   └── Support/            # Helpers, Money, etc.
├── bootstrap/             # Bootstrap y middleware aliases
├── config/                 # Configuración por entorno
├── database/
│   ├── factories/         # Factories para tests
│   ├── migrations/         # 88 migraciones
│   └── seeders/            # Seeders (Database, AdminUser, Permissions)
├── public/                 # Document root (entry point)
├── resources/              # Views, CSS, JS
├── routes/                 # api.php, web.php, console.php
├── storage/                # Logs, cache, uploads
└── tests/                  # Feature + Unit tests
```

## Comandos principales

### Levantar el servidor de desarrollo

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Esto arranca el servidor en `http://127.0.0.1:8000`.

### Migraciones y seeders

```bash
# Correr migraciones
php artisan migrate

# Correr seeders
php artisan db:seed

# Reset completo (¡borra datos!)
php artisan migrate:fresh --seed
```

### Tests

```bash
# Toda la suite
composer test

# Test específico
php artisan test --compact --filter=login_with_valid_credentials

# Solo los del grupo auth
php artisan test --compact --group=auth
```

### Linter y análisis estático

```bash
# Formatear código (Pint)
composer format

# Solo verificar formato sin tocar archivos
composer lint

# Verificar archivos modificados
composer lint:dirty

# Análisis estático (PHPStan level 7)
composer analyse

# Pre-commit completo
composer precommit
```

### Consola interactiva

```bash
php artisan tinker
```

## Variables de entorno

Archivo `backend/.env` (basado en `.env.example`):

```env
APP_NAME=Ecommerce
APP_ENV=local
APP_KEY=base64:GENERAR_CON_php_artisan_key:generate
APP_DEBUG=true
APP_URL=http://localhost:9000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=store
DB_USERNAME=postgres
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

SANCTUM_STATEFUL_DOMAINS=panda-estampados.com,kitsune.com
SESSION_DOMAIN=.localhost
```

## ORM y base de datos

- `PostgreSQL` con `PostGIS` para datos geoespaciales.
- `Eloquent ORM` con relaciones tipadas.
- `Migrations` versionadas en `database/migrations/`.
- 88 migraciones, 75 tablas.
- `Money` en centavos (`unsignedBigInteger`) — nunca `decimal`.

## Multi-tenancy

Ver [architecture/multi-store.md](../architecture/multi-store.md "architecture/multi-store.md").

- Cada tienda es un `Store` con `store_id` en sus datos.
- Middleware `store.domain` resuelve la tienda activa por host.
- `BelongsToStore` trait aplica global scope automático.
- Asociación `users ↔ stores` vía pivot `user_stores`.

## Testing

- `PHPUnit` 12 con `RefreshDatabase` trait.
- 142 tests (al día de la última limpieza).
- Cobertura crítica: Auth, Multi-tenant, Cart, Checkout, Payments,
  Shipments, Refunds, RBAC.

## Documentación relacionada

- [architecture/overview.md](../architecture/overview.md "architecture/overview.md")
- [architecture/multi-store.md](../architecture/multi-store.md "architecture/multi-store.md")
- [getting-started/backend-setup.md](../getting-started/backend-setup.md "getting-started/backend-setup.md")
- [CONTRIBUTING.md](../../CONTRIBUTING.md "CONTRIBUTING.md")
- [backend/README.md](../backend/README.md "backend/README.md")
