# Panda Estampados / Kitsune Store

Sistema web de gestión empresarial para la comercialización de merchandising personalizado, productos de cultura japonesa, videojuegos, anime, artículos textiles, cerámica, vidrio, madera, aluminio y regalos corporativos.

## Descripción

La plataforma funciona como:

- **Vitrina digital** — catálogo público de productos con precios, disponibilidad e interacción por WhatsApp.
- **Panel administrativo** — gestión de clientes, productos, categorías, proveedores, compras, facturas, reportes, respaldos, auditoría, logs y notificaciones internas.

Soporta **multi-tienda** con resolución por dominio:

| Tienda | Enfoque |
| --- | --- |
| **Panda Estampados** | B2B / corporativo — estampados personalizados |
| **Kitsune Store** | B2C — cultura anime, manga y japonesa |

## Stack Tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Sanctum |
| Frontend | Astro 7, React 19, Tailwind CSS 4, TypeScript |
| Base de datos | PostgreSQL 18, PostGIS |
| Infraestructura | Docker Compose |
| Testing | PHPUnit 12 |
| Reportes | PhpSpreadsheet, Chart.js |

## Estructura del Proyecto

```txt
Ecommerce/
├── backend/          # API REST — Laravel 13
├── frontend/         # Interfaz web — Astro 7 + React 19
├── docs/             # Documentación técnica y de diseño
├── docker/           # Docker Compose (dev, tools)
└── database/         # Migraciones, seeds, esquemas
```

## Requisitos

- PHP 8.3+
- PostgreSQL 18+
- Node.js 20+
- Docker Compose (opcional, para entorno reproducible)

## Instalación

### Con Docker (recomendado)

```bash
# Clonar el repositorio
git clone <url-del-repositorio>
cd Ecommerce

# Levantar servicios
cd docker/dev
docker compose -f docker-compose.dev.yml up -d

# Ejecutar migraciones
cd ../../backend
php artisan migrate

# Ejecutar seeders (datos iniciales)
php artisan db:seed
```

### Sin Docker

```bash
# Instalar dependencias PHP
cd backend
composer install

# Configurar base de datos
cp .env.example .env
php artisan key:generate

# Editar .env con tus credenciales de PostgreSQL
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=ecommerce
# DB_USERNAME=tu_usuario
# DB_PASSWORD=tu_contraseña

# Ejecutar migraciones
php artisan migrate

# Instalar dependencias del frontend
cd ../frontend
npm install
```

## Comandos de Desarrollo

### Backend

```bash
cd backend

# Instalar dependencias
composer install

# Servidor de desarrollo
composer run dev

# Migraciones
php artisan migrate

# Pruebas
php artisan test

# Formateo de código
vendor/bin/pint

# Análisis estático
vendor/bin/phpstan analyse
```

### Frontend

```bash
cd frontend

# Instalar dependencias
npm install

# Desarrollo
npm run dev

# Build de producción
npm run build
```

## Módulos del Sistema

| Módulo | Descripción |
| --- | --- |
| **Auth** | Autenticación, 2FA (email OTP, TOTP, Passkeys), roles y permisos |
| **Catálogo** | Marcas, categorías, productos, variantes, atributos, imágenes |
| **Inventario** | Stock, movimientos, almacenes |
| **Clientes** | Registro, direcciones, historial |
| **Ventas** | Órdenes, facturas, historial de estados |
| **Pagos** | Métodos de pago, transacciones, cuotas |
| **Compras** | Órdenes de compra, proveedores, ítems |
| **Devoluciones** | Solicitudes de devolución, reembolsos |
| **Carrito** | Carrito de compras, lista de deseos |
| **Envíos** | Empresas de envío, seguimiento |
| **Marketing** | Cupones, promociones |
| **Impuestos** | Configuración de impuestos |
| **Soporte** | Tickets de soporte |
| **Medios** | Gestión de imágenes y archivos |
| **Configuración** | Ajustes generales por tienda |
| **Tiendas** | Multi-tienda, dominios, configuración por store |
| **Reportes** | Dashboard, métricas, exportación a Excel |
| **Sistema** | Auditoría, logs, respaldos, notificaciones |

## Infraestructura Docker

```yaml
Servicios:
  postgres:    PostgreSQL 18 + PostGIS
  pgadmin:     Administración visual de BD
  backups:     Scheduler de respaldos automáticos
```

## Base de datos

La lógica de negocio se apoya en:

- Funciones almacenadas y procedimientos
- Triggers para auditoría e historial de estados
- Vistas para reportes
- Índices para optimización de consultas
- Restricciones de integridad referencial

## Seguridad

- Autenticación con tokens Bearer (Sanctum)
- 2FA: email OTP, TOTP, Passkeys (preparado)
- Roles y permisos por módulo
- Auditoría de eliminaciones
- Logs de actividad
- Respaldos automáticos y restauración

## Documentación

La documentación detallada se encuentra en:

- [`docs/`](docs/) — Arquitectura, diseño, operaciones
- [`backend/DOCUMENTACION.md`](backend/DOCUMENTACION.md) — Documentación técnica del backend
- [`backend/docs/`](backend/docs/) — Docs internos del backend

## Licencia

MIT
