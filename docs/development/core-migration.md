# Migración al namespace `Core\\`

Guía para contribuidores que migran código legacy a la estructura
modular de Fase 5.

## Contexto

`backend/app/Core/` ya aloja los servicios de aplicación del core del
sistema. La organización sigue módulos de dominio:

```txt
app/Core/
├── Auth/
├── Catalog/
├── Cart/
├── Checkout/
├── Orders/
├── Payments/
├── Shipping/
├── Tenant/
│   └── Application/
│       ├── StoreContextService.php
│       └── RlsContextService.php
└── Shared/
    ├── Events/
    │   ├── Event.php
    │   ├── EventBus.php
    │   └── InProcessEventBus.php
    └── Plugins/
```

Composer expone `Core\\` desde `psr-4: { "Core\\": "app/Core/" }` y la
carpeta `app/` legacy sigue funcionando con `App\\` para los archivos
que aún no se migraron.

## Reglas

- **Nuevo código** siempre importa desde `Core\…`.
- **Código legacy** puede seguir usando `App\Services\…` hasta el
  release `v2.0.0`.
- Los archivos `App\…` que se reescriben deben heredar o reexportar
  desde la versión `Core\…` para mantener la API pública.
- El contenedor debe registrar la implementación en `Core\…` y mapear
  cualquier alias legacy.
- Cada migration completa un módulo: 5.1 (Tenant), 5.2 (Shared/Events),
  5.3 (RLS), 5.4 (Theme packages npm), 5.5 (cleanup de aliases).

## Procedimiento de migración por servicio

1. Crear la implementación final en `app/Core/<Módulo>/Application/`.
2. Registrar el binding en `AppServiceProvider::register()`.
3. Reexportar desde la ruta legacy como `class ... extends CoreImpl {}`
   o como alias (`use`).
4. Buscar todos los `use App\…\…` y reescribirlos a `use Core\…\…`.
5. Correr la suite completa: debe pasar sin cambios de contrato.
6. Actualizar el roadmap y `docs/architecture/decisions.md`.

## Ejemplo: `StoreContextService`

La implementación vive en
`backend/app/Core/Tenant/Application/StoreContextService.php`. El wrapper
legacy en `app/Services/Stores/StoreContextService.php` solo extiende la
clase Core; ningún consumidor lo implementa directamente.

`AppServiceProvider` mantiene dos bindings equivalentes:

```php
use App\Services\Stores\StoreContextService;
use Core\Tenant\Application\StoreContextService as CoreStoreContextService;

$this->app->singleton(StoreContextService::class);
$this->app->alias(StoreContextService::class, CoreStoreContextService::class);
```

Cualquier consumidor actual sigue funcionando, pero la guía instruye a
importar `Core\Tenant\Application\StoreContextService` en código nuevo.

## Compatibilidad con plugins Composer

Los plugins Composer (ej. `ecommerce-plugin-erp`) deben importar
`Core\Tenant\Application\StoreContextService` y
`Core\Tenant\Application\RlsContextService` en lugar de las rutas legacy.
El binding singleton del contenedor sigue disponible vía `app(...)`.

## Checklist de cierre de F5.5

- [ ] Ningún archivo de `app/` consume el wrapper
  `App\Services\Stores\StoreContextService` directamente.
- [ ] El `AppServiceProvider` sigue resolviendo una sola instancia para ambas clases.
- [ ] `php artisan test --compact` pasa sin warnings nuevos.
- [ ] `vendor/bin/pint` y `vendor/bin/phpstan` sin nuevos errores.
- [ ] `markdownlint` pasa sobre `docs/`.
- [ ] El roadmap marca F5.5 como completada y propone el tag `v2.0.0`.

## Próximo paso

`v2.0.0` removerá `app/Services/Stores/StoreContextService.php` y
declarará la migración completa a `app/Core/`. Cada módulo
(`Auth`, `Catalog`, `Orders`, etc.) seguirá la misma receta cuando su
volumen justifique el corte.
