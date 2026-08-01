# `Composer` en `Laravel`

`Composer` se usa para instalar y administrar paquetes
`PHP` propios del proyecto `Laravel`. En esta
configuración se agregaron herramientas de desarrollo
para mejorar el autocompletado, el formateo y el análisis
estático del código.

## Paquetes instalados

Los paquetes instalados como dependencias de desarrollo
fueron:

```bash
composer require --dev \
  barryvdh/laravel-ide-helper \
  laravel/pint \
  larastan/larastan
```

Estos paquetes quedan registrados en la sección
`require-dev` del archivo `composer.json`.

- `barryvdh/laravel-ide-helper`
  - Genera archivos de ayuda para mejorar el autocompletado
    de `Laravel` con `Intelephense/IDE`
- `laravel/pint`
  - Formatea automáticamente el código `PHP` siguiendo el
    estilo de `Laravel`
- `larastan/larastan`
  - Agrega análisis estático para `Laravel` usando
    `PHPStan`

---

## `Laravel IDE Helper`

`Laravel` usa muchas características dinámicas como
Facades, helpers, modelos `Eloquent` y métodos mágicos.
Por eso, algunas herramientas de autocompletado no siempre
entienden todo el proyecto de forma automática.

Para mejorar el autocompletado se usa:

```bash
barryvdh/laravel-ide-helper
```

### Comandos principales

```bash
php artisan ide-helper:generate
php artisan ide-helper:models -N
php artisan ide-helper:meta
```

También se puede ejecutar todo con `Composer` si se
agrega un script:

```bash
composer ide-helper
```

### Archivos generados

Estos comandos generan archivos como:

```txt
_ide_helper.php
_ide_helper_models.php
.phpstorm.meta.php
```

Estos archivos ayudan al LSP o IDE a entender mejor:

- Facades de `Laravel`.
- Modelos `Eloquent`.
- Métodos mágicos.
- Helpers.
- Relaciones entre modelos.
- Propiedades dinámicas.

Ejemplo: después de generar estos archivos, el
autocompletado puede entender mejor llamadas como:

```php
User::query()
Route::get()
Auth::user()
```

---

## `Laravel Pint`

`Laravel Pint` se usa para formatear el código `PHP`
del proyecto.

Ejecutar `Pint` directamente:

```bash
./vendor/bin/pint
```

O usando `Composer`:

```bash
composer format
```

`Pint` corrige automáticamente detalles de estilo como:

- Posición de llaves.
- Espacios.
- Líneas en blanco.
- Saltos de línea al final de archivo.
- Estilo general recomendado por `Laravel`.

---

## `Larastan` / `PHPStan`

`Larastan` permite analizar el código `Laravel` de forma
estática para detectar posibles errores antes de ejecutar
la aplicación.

Para configurarlo se creó el archivo:

```txt
phpstan.neon
```

Contenido recomendado:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 5

    paths:
        - app
        - routes
        - database

    excludePaths:
        - vendor
        - storage
        - bootstrap/cache
        - _ide_helper.php
        - _ide_helper_models.php
        - .phpstorm.meta.php
```

La sección importante es:

```neon
paths:
    - app
    - routes
    - database
```

Sin esa sección, `PHPStan` puede mostrar el error:

```txt
At least one path must be specified to analyse.
```

---

## Scripts agregados en `composer.json`

Para facilitar el uso de estas herramientas, se agregaron
scripts dentro de la sección `"scripts"` de
`composer.json`.

```json
"ide-helper": [
    "@php artisan ide-helper:generate",
    "@php artisan ide-helper:models -N",
    "@php artisan ide-helper:meta"
],
"format": [
    "./vendor/bin/pint"
],
"analyse": [
    "./vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G"
]
```

Con estos scripts, ya no es necesario escribir los
comandos completos.

### Uso

Generar archivos de ayuda para autocompletado:

```bash
composer ide-helper
```

Formatear el código `PHP` con `Pint`:

```bash
composer format
```

Analizar el código con `Larastan`/`PHPStan`:

```bash
composer analyse
```

---

## Archivos recomendados en `.gitignore`

Los archivos generados por `Laravel IDE Helper` pueden
ignorarse si solo se usan localmente para mejorar el
autocompletado.

Agregar al archivo `.gitignore`:

```gitignore
# Laravel IDE Helper
_ide_helper.php
_ide_helper_models.php
.phpstorm.meta.php
```

Esto evita subir al repositorio archivos generados
automáticamente.

---

## Flujo recomendado de uso

Después de instalar dependencias o cambiar modelos, rutas
o estructura importante del proyecto:

```bash
composer dump-autoload
composer ide-helper
composer format
composer analyse
```

Este flujo permite:

1. Actualizar el autoload de `Composer`.
2. Regenerar archivos de autocompletado para `Laravel`.
3. Formatear el código.
4. Analizar posibles errores con `Larastan`/`PHPStan`.

---

## Resultado esperado

Al ejecutar:

```bash
composer ide-helper
composer format
composer analyse
```

Se espera que:

- `composer ide-helper` genere los archivos
  `_ide_helper.php`, `_ide_helper_models.php` y
  `.phpstorm.meta.php`.
- `composer format` corrija automáticamente el estilo
  del código `PHP`.
- `composer analyse` revise el proyecto usando
  `Larastan`/`PHPStan`.

Si `composer analyse` muestra errores, esos errores deben
revisarse porque pueden indicar problemas reales de tipos,
modelos, rutas o código `Laravel`.
