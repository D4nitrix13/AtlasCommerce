# Backend — Panda Estampados / Kitsune Store

API REST construida con **Laravel 13** y **PHP 8.3+**.

## Stack

| Componente        | Versión | Propósito                     |
| ----------------- | ------- | ----------------------------- |
| PHP               | ^8.3    | Lenguaje del servidor         |
| Laravel Framework | ^13.8   | Framework MVC                 |
| Laravel Sanctum   | ^4.0    | Autenticación API por tokens  |
| Laravel Boost     | ^2.2    | Herramientas de desarrollo AI |
| Laravel Pint      | ^1.29   | Formateo de código            |
| PHPUnit           | ^12.5   | Testing                       |
| PostgreSQL        | 18+     | Base de datos principal       |
| PostGIS           | —       | Extensiones geoespaciales     |

## Estructura

```txt
backend/
├── app/
│   ├── Http/Controllers/Api/   # Controllers organizados por dominio
│   ├── Models/                 # Modelos Eloquent
│   ├── Services/               # Lógica de negocio
│   └── Resources/              # API Resources
├── config/                     # Configuración de Laravel
├── database/
│   ├── migrations/             # Migraciones de la BD
│   ├── factories/              # Factories para testing
│   └── seeders/                # Seeders (datos iniciales)
├── routes/                     # Rutas API
├── tests/                      # Pruebas PHPUnit
└── DOCUMENTACION.md            # Documentación técnica completa
```

## Instalación

```bash
# Instalar dependencias
composer install

# Copiar variables de entorno
cp .env.example .env

# Generar APP_KEY
php artisan key:generate

# Configurar .env con tu PostgreSQL
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=ecommerce
# DB_USERNAME=tu_usuario
# DB_PASSWORD=tu_contraseña

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed
```

## Comandos de Desarrollo

```bash
# Servidor completo (API + Queue + Logs + Vite)
composer run dev

# Solo servidor Laravel
php artisan serve

# Migraciones
php artisan migrate
php artisan migrate:refresh    # Reset + re-ejecutar
php artisan migrate:fresh --seed  # Limpiar + migrar + seed

# Pruebas
php artisan test
php artisan test --compact
php artisan test --filter=nombreDelTest

# Formateo
vendor/bin/pint

# Análisis estático
vendor/bin/phpstan analyse

# Generar IDE helpers
composer ide-helper
```

## Autenticación

- Tokens Bearer con Laravel Sanctum
- 2FA: email OTP (implementado), TOTP y Passkeys (preparados)
- Roles: Administrador, Supervisor, Facturador
- Permisos por módulo

## Módulos API

| Módulo        | Rutas                                        |
| ------------- | -------------------------------------------- |
| Auth          | `/api/v1/auth/*`                             |
| Catálogo      | `/api/v1/products/*`, `/api/v1/categories/*` |
| Inventario    | `/api/v1/inventory/*`                        |
| Clientes      | `/api/v1/customers/*`                        |
| Ventas        | `/api/v1/orders/*`, `/api/v1/invoices/*`     |
| Pagos         | `/api/v1/payments/*`                         |
| Compras       | `/api/v1/purchases/*`                        |
| Devoluciones  | `/api/v1/returns/*`                          |
| Carrito       | `/api/v1/cart/*`                             |
| Envíos        | `/api/v1/shipments/*`                        |
| Configuración | `/api/v1/settings/*`                         |

## Documentación

- [`DOCUMENTACION.md`](DOCUMENTACION.md) — Documentación técnica completa del backend
- [`docs/`](docs/) — Documentación interna adicional
