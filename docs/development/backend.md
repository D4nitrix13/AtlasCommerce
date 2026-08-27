# Backend — Comandos del día a día

Comandos frecuentes para el backend Laravel.

## Servidor de desarrollo

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Servidor: `http://127.0.0.1:8000`.

Pack completo con queue + logs + vite (alternativa):

```bash
composer dev
```

## Tests

Toda la suite:

```bash
composer test
```

Alias de:

```bash
php artisan config:clear
php artisan test
```

Test específico:

```bash
php artisan test --compact --filter=<TestName>
```

Por archivo:

```bash
php artisan test tests/Feature/Auth/AuthHttpTest.php
```

Por grupo:

```bash
php artisan test --group=auth
php artisan test --group=http
```

Detener después del primer fallo:

```bash
php artisan test --stop-on-failure
```

Con coverage:

```bash
php artisan test --coverage
php artisan test --coverage-html=storage/coverage
```

## Linter y calidad

```bash
# Formatear código
composer format

# Verificar formato (no toca archivos)
composer lint

# Solo archivos modificados
composer lint:dirty

# Análisis estático (PHPStan level 7)
composer analyse

# Pipeline completo pre-commit
composer precommit
```

## Migraciones

```bash
# Crear nueva migración
php artisan make:migration create_<name>_table

# Correr migraciones pendientes
php artisan migrate

# Revertir última migración
php artisan migrate:rollback

# Reset completo (¡borra datos!)
php artisan migrate:fresh --seed
```

Ver estado:

```bash
php artisan migrate:status
```

## Seeders

```bash
# Correr todos los seeders
php artisan db:seed

# Correr un seeder específico
php artisan db:seed --class=AdminUserSeeder

# Con progreso
php artisan db:seed --verbose
```

## Rutas

```bash
# Listar todas las rutas
php artisan route:list

# Filtrar por path
php artisan route:list --path=api/auth

# Filtrar por nombre
php artisan route:list --name=auth

# Solo rutas del proyecto (no vendor)
php artisan route:list --except-vendor
```

## Cache

```bash
# Limpiar todo
php artisan optimize:clear

# Cache específico
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## Logs

```bash
# Ver logs en vivo
tail -f storage/logs/laravel.log

# Limpiar logs
> storage/logs/laravel.log
```

## Consola interactiva

```bash
# Tinker (REPL PHP con el framework cargado)
php artisan tinker

# Ejemplo de uso
>>> User::count()
=> 1
>>> User::find(1)->email
=> "admin@panda-estampados-and-kitsune.test"
```

Para debug rápido:

```bash
php artisan tinker --execute='
echo User::count();
'
```

## IDE Helper

Genera archivos de autocompletado para IDEs:

```bash
composer ide-helper
```

Genera:

- `_ide_helper.php` — para Laravel classes.
- `app/Models/_ide_helper_models.php` — para modelos.

## Comandos artisan custom del proyecto

```bash
# Listar todos los comandos
php artisan list

# Buscar comandos por nombre
php artisan list | grep <keyword>
```

## Optimización para producción

```bash
# Cache de configuración, rutas y vistas
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimización combinada
php artisan optimize
```

## Problemas comunes

### `Class 'X' not found`

```bash
composer dump-autoload
```

### Cambios en `.env` no se aplican

```bash
php artisan config:clear
```

### Cambios en providers no se aplican

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Artisan no responde

```bash
# Verificar permisos de storage y bootstrap/cache
sudo chown -R $USER:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Próximo archivo

[testing.md](testing.md "testing.md") — Estrategias de testing.
