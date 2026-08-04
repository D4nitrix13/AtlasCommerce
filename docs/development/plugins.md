# Plugins Composer

Los plugins Composer contienen features grandes que no deben acoplarse al core
Laravel. Cada plugin puede registrar rutas, migraciones, configuración y vistas,
y se activa por tenant mediante una feature flag.

## Quick path

1. Define un paquete con nombre `d4nitrix13/ecommerce-plugin-{feature}`.
2. Declara el `ServiceProvider` en `extra.laravel.providers`.
3. Registra las rutas, migraciones, configuración y vistas desde el provider.
4. Protege las rutas con un middleware que consulte la tienda actual.
5. Agrega tests del paquete y tests de integración en el backend.
6. Instala el paquete en cada instancia que necesite la feature.

El paquete de referencia está en
`backend/packages/ecommerce-plugin-erp`.

## Cuándo crear un plugin

Usa un plugin cuando una feature tenga varias de estas características:

- Tiene cientos de archivos o un dominio funcional completo.
- Requiere migraciones y modelos propios.
- Depende de un servicio externo.
- Solo aplica a algunas instancias o tenants.
- Puede evolucionar sin modificar el ciclo principal de ventas.

Mantén la feature en el core cuando sea pequeña, transversal o necesaria para
todas las tiendas. La secuencia recomendada es:

```txt
core estable → feature flag → configuración por instancia → plugin
```

## Estructura mínima

```txt
packages/ecommerce-plugin-erp/
├── composer.json
├── config/
│   └── erp.php
├── database/
│   └── migrations/
│       └── *_create_erp_connections_table.php
├── resources/
│   └── views/
│       └── erp/
├── routes/
│   └── api.php
├── src/
│   ├── EcommercePluginErpServiceProvider.php
│   └── Http/
│       ├── Controllers/
│       └── Middleware/
└── tests/
    └── PackageMetadataTest.php
```

El paquete puede vivir temporalmente bajo `backend/packages/` usando un
repositorio Composer `path`. Cuando tenga ciclo de vida propio, debe extraerse
a un repositorio Composer independiente sin cambiar su namespace público.

## Metadata de Composer

El `composer.json` debe declarar el tipo de paquete, el autoload PSR-4 y el
provider Laravel:

```json
{
    "name": "d4nitrix13/ecommerce-plugin-erp",
    "description": "ERP integration plugin for the Ecommerce platform.",
    "type": "ecommerce-plugin",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.8"
    },
    "autoload": {
        "psr-4": {
            "D4nitrix13\\EcommercePluginErp\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "D4nitrix13\\EcommercePluginErp\\EcommercePluginErpServiceProvider"
            ]
        }
    }
}
```

`extra.laravel.providers` es el contrato de auto-discovery. No agregues el
provider manualmente en `config/app.php` ni en `bootstrap/providers.php`.

## ServiceProvider

El provider registra todos los recursos que el plugin necesita:

```php
final class EcommercePluginErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/erp.php',
            key: 'erp',
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(path: __DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');
        $this->loadViewsFrom(
            path: __DIR__.'/../resources/views',
            namespace: 'ecommerce-plugin-erp',
        );
    }
}
```

`loadMigrationsFrom` carga las migraciones directamente desde el paquete.
No combines ese registro con una copia de las mismas migraciones publicada en
`database/migrations`, porque Laravel podría intentar registrarlas dos veces.

Si la aplicación necesita editar la configuración o las vistas publicadas,
usa tags específicos:

```bash
cd backend
php artisan vendor:publish \
  --tag=ecommerce-plugin-erp-config
php artisan vendor:publish \
  --tag=ecommerce-plugin-erp-views
```

## Rutas protegidas por tenant

Las rutas del plugin deben incluir `store.domain` antes del middleware de la
feature:

```php
Route::prefix('api')
    ->middleware(['api', 'store.domain', EnsureErpFeatureEnabled::class])
    ->get('erp/status', ErpStatusController::class)
    ->name('erp.status');
```

El orden importa:

1. `store.domain` busca el dominio y establece el `StoreContextService`.
2. `EnsureErpFeatureEnabled` consulta la tienda actual.
3. El controller se ejecuta solo si `Store::hasFeature('erp')` es `true`.

El provider no debe ejecutar `hasFeature()` desde `boot()`. El provider se
inicializa al arrancar la aplicación, antes de que exista una request y antes
de resolver el dominio del tenant. El aislamiento por tenant pertenece al
middleware de request.

Cuando la feature está desactivada, el plugin responde `404`:

```json
{
    "message": "Feature not enabled for this store."
}
```

El `404` evita revelar a un tenant que existe una integración instalada pero
no habilitada para él.

## Feature flag por instancia

Declara la feature en el JSON usado por `tenants:bootstrap`:

```json
{
    "tenants": [
        {
            "slug": "panda-estampados",
            "features": {
                "erp": true
            }
        },
        {
            "slug": "kitsune",
            "features": {
                "erp": false
            }
        }
    ]
}
```

La instalación del paquete no activa automáticamente la feature. El plugin
puede estar instalado en una instancia y permanecer desactivado para todos
sus tenants.

Después de cambiar el JSON, ejecuta el bootstrap de la instancia:

```bash
cd backend
docker compose exec app php artisan tenants:bootstrap \
  docker/instances/panda-kitsune/seeds/panda.json
```

## Instalar un plugin

En una aplicación que consume un paquete publicado:

```bash
cd backend
composer require d4nitrix13/ecommerce-plugin-erp
php artisan migrate --force
php artisan package:discover
```

En desarrollo local, el backend usa un repositorio `path` con symlink:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/ecommerce-plugin-erp",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Luego instala la dependencia con la versión que Composer haya detectado para
el repositorio local:

```bash
composer update d4nitrix13/ecommerce-plugin-erp --with-dependencies
```

Confirma el auto-discovery con:

```bash
php artisan route:list --path=api/erp --except-vendor
php artisan migrate:status
```

La ruta debe aparecer como `erp.status` y la migración del plugin debe figurar
en el estado de migraciones.

## Tests requeridos

Cada plugin debe tener tests independientes para su metadata y sus clases.
El backend consumidor debe tener tests de integración para auto-discovery y
multi-tenancy.

Tests propios del paquete:

```bash
cd backend
vendor/bin/phpunit \
  --configuration packages/ecommerce-plugin-erp/phpunit.xml
```

Tests de integración del backend:

```bash
cd backend
php artisan test --compact \
  tests/Feature/Plugins/ErpPluginDiscoveryTest.php \
  tests/Feature/Plugins/ErpPluginFeatureGateTest.php
```

Como mínimo, los tests deben comprobar:

- El provider está declarado en `extra.laravel.providers`.
- Laravel carga el provider sin registro manual.
- Las rutas del plugin aparecen después de `package:discover`.
- Un tenant habilitado puede usar la feature.
- Un tenant deshabilitado recibe `404`.
- Dos tenants con flags distintas permanecen aislados.
- Las migraciones del plugin se pueden ejecutar y revertir.

## Checklist de publicación

- [ ] El nombre del paquete usa `ecommerce-plugin-{feature}`.
- [ ] El paquete declara `type: ecommerce-plugin`.
- [ ] El namespace PSR-4 no colisiona con el core ni con otro plugin.
- [ ] El provider está declarado en `extra.laravel.providers`.
- [ ] Rutas, migraciones, vistas y configuración se registran desde el provider.
- [ ] Las rutas usan `store.domain` antes del gate de la feature.
- [ ] La feature flag está documentada y tiene un slug estable.
- [ ] Las migraciones no duplican nombres ni tablas del core.
- [ ] Existen tests propios y tests de integración multi-tenant.
- [ ] `composer validate --strict` pasa.
- [ ] Pint, PHPStan y PHPUnit pasan en el alcance disponible.
- [ ] El paquete se probó en una instancia con la feature activa y otra
      desactivada.

## Referencias del proyecto

- [Roadmap multi-instance](../architecture/multi-instance-roadmap.md)
- [Feature flags por tienda](../architecture/multi-store.md)
- [Despliegue por instancia](../deployment/instances.md)
- [Testing del proyecto](testing.md)
- [Linting del proyecto](linting.md)

## Próximo paso

La Fase 4 queda completa con esta guía. La siguiente fase del roadmap es la
migración gradual hacia `app/Core/`, el event bus in-process y PostgreSQL RLS.
