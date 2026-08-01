# Backend Laravel - HojasTech

Este directorio contiene el backend inicial de **HojasTech**, una plataforma web y móvil orientada a la asistencia agrícola.

El backend fue iniciado con **Laravel** como tecnología propuesta para construir la API principal del sistema. Esta decisión todavía debe considerarse en evaluación hasta que sea registrada formalmente en la documentación técnica del proyecto.

## Estado actual

| Elemento                                       | Estado                                                  |
| ---------------------------------------------- | ------------------------------------------------------- |
| Framework backend                              | Laravel                                                 |
| Ubicación del proyecto                         | `backend/api`                                           |
| Sistema operativo host                         | CachyOS basado en Arch Linux                            |
| Base de datos seleccionada durante instalación | PostgreSQL                                              |
| Starter kit                                    | None                                                    |
| Framework de pruebas                           | PHPUnit                                                 |
| Laravel Boost                                  | Seleccionado durante instalación                        |
| Frontend web                                   | React separado en `frontend/web`, pendiente de creación |
| Docker                                         | Pendiente de configuración para entorno de desarrollo   |
| Migraciones iniciales                          | Pendientes hasta crear o levantar PostgreSQL            |

## Objetivo de este README

Este fichero documenta la instalación inicial del backend Laravel, los comandos ejecutados o recomendados, el significado de cada comando, la configuración necesaria de PHP en el host, las razones de las opciones seleccionadas durante la instalación, cómo verificar que todo esté configurado correctamente y cómo se relaciona esta instalación con el futuro entorno Docker del proyecto.

## Contexto técnico

HojasTech busca mantener una arquitectura modular y ordenada. Por eso, el backend se ubica dentro del directorio:

```bash
backend/api
```

La estructura permite separar el backend de otros componentes futuros, como:

```bash
frontend
database
docker
monitoring
scripts
docs
```

Aunque actualmente se está configurando Laravel en el host, el objetivo del proyecto es usar Docker para levantar los servicios de desarrollo de forma reproducible.

## Decisión sobre el Starter Kit

Durante la instalación de Laravel se seleccionó:

```bash
Starter kit: None
```

Esta decisión es correcta para HojasTech porque el proyecto busca separar el backend del frontend.

Laravel puede trabajar con starter kits que generan una base inicial con autenticación, frontend, rutas, estilos y estructura de interfaz. Sin embargo, para HojasTech se prefiere que Laravel funcione como backend API y que React viva en un directorio separado.

La estructura recomendada es:

```bash
backend/api
frontend/web
```

Con esta separación, Laravel se encargará principalmente de:

```bash
API REST
autenticación
validaciones
reglas de negocio
modelos
migraciones
servicios internos
conexión con PostgreSQL
```

React se encargará principalmente de:

```bash
interfaz web
componentes visuales
formularios
rutas del cliente
consumo de la API
experiencia de usuario
```

## Por qué no se eligió React Starter Kit

Aunque HojasTech probablemente usará React para el frontend web, no se eligió el starter kit de React porque ese enfoque integra React dentro del proyecto Laravel.

El starter kit de React de Laravel está pensado para aplicaciones donde Laravel y React trabajan juntos dentro del mismo proyecto, normalmente usando Inertia. Ese modelo puede ser útil para aplicaciones monolíticas modernas, pero no es el enfoque más conveniente para HojasTech en esta etapa.

Para HojasTech se prefiere no usar React dentro de Laravel por estas razones:

| Razón                           | Explicación                                                                              |
| ------------------------------- | ---------------------------------------------------------------------------------------- |
| Separación de responsabilidades | Laravel queda como backend API y React queda como frontend independiente.                |
| Arquitectura modular            | Cada componente del sistema puede evolucionar sin depender directamente del otro.        |
| Preparación para app móvil      | Una API separada podrá ser consumida por frontend web y futura app móvil.                |
| Docker más claro                | Cada servicio puede tener su propio contenedor o configuración.                          |
| Documentación evolutiva         | React puede seguir en evaluación sin quedar acoplado al backend desde el inicio.         |
| Menor acoplamiento              | Cambiar React por Vue u otra tecnología sería más sencillo si el frontend está separado. |
| Organización profesional        | La estructura `backend/` y `frontend/` facilita mantenimiento y despliegue.              |

La decisión actual no significa que React esté descartado. Significa que React se agregará después como proyecto separado.

## Cuándo sí tendría sentido usar React Starter Kit

El starter kit de React tendría sentido si HojasTech fuera una sola aplicación Laravel con frontend integrado.

Sería una opción razonable si se quisiera:

```bash
usar Laravel e Inertia juntos
tener React dentro de resources/js
manejar rutas web desde Laravel
usar autenticación ya generada por Laravel
mantener backend y frontend en un solo proyecto
avanzar rápido con una aplicación monolítica
```

Sin embargo, ese no es el enfoque recomendado actualmente para HojasTech.

## Decisión recomendada para HojasTech

La decisión recomendada para esta etapa es:

| Área                 | Decisión                          |
| -------------------- | --------------------------------- |
| Backend              | Laravel en `backend/api`          |
| Starter kit          | None                              |
| Frontend web         | React separado en `frontend/web`  |
| Comunicación         | API HTTP entre frontend y backend |
| Base de datos        | PostgreSQL                        |
| Entorno reproducible | Docker Compose                    |
| Pruebas backend      | PHPUnit                           |

## Flujo previsto entre frontend y backend

El flujo esperado será:

```bash
Usuario
Frontend React
API Laravel
PostgreSQL
```

En desarrollo local con Docker, el flujo esperado será parecido a:

```bash
frontend-web
backend-api
postgres
redis
```

El frontend React consumirá endpoints expuestos por Laravel.

Ejemplo conceptual:

```bash
GET /api/crops
POST /api/crops
GET /api/products
POST /api/auth/login
```

Estos endpoints todavía no están implementados. Se documentan como referencia conceptual para la arquitectura futura.

## Comandos usados para preparar el host

### Actualizar el sistema

```bash
sudo pacman -Syu
```

Este comando actualiza la base de datos de paquetes y sincroniza el sistema en CachyOS/Arch Linux.

| Parte    | Descripción                                                  |
| -------- | ------------------------------------------------------------ |
| `sudo`   | Ejecuta el comando con permisos de administrador.            |
| `pacman` | Gestor de paquetes de Arch Linux y distribuciones derivadas. |
| `-S`     | Sincroniza paquetes desde los repositorios.                  |
| `-y`     | Actualiza la base de datos local de paquetes.                |
| `-u`     | Actualiza los paquetes instalados en el sistema.             |

### Instalar herramientas base

```bash
sudo pacman -S php composer nodejs npm git unzip
```

Este comando instala las herramientas necesarias para crear y ejecutar un proyecto Laravel desde el host.

| Paquete    | Propósito                                                               |
| ---------- | ----------------------------------------------------------------------- |
| `php`      | Lenguaje requerido por Laravel.                                         |
| `composer` | Gestor de dependencias de PHP.                                          |
| `nodejs`   | Entorno necesario para herramientas frontend y Vite.                    |
| `npm`      | Gestor de paquetes de Node.js.                                          |
| `git`      | Control de versiones del proyecto.                                      |
| `unzip`    | Utilidad necesaria para descomprimir paquetes descargados por Composer. |

## Instalación del instalador de Laravel

```bash
composer global require laravel/installer
```

Este comando instala el instalador de Laravel de forma global para poder crear proyectos usando:

```bash
laravel new
```

| Parte               | Descripción                                                                     |
| ------------------- | ------------------------------------------------------------------------------- |
| `composer`          | Ejecuta Composer.                                                               |
| `global`            | Instala el paquete para el usuario actual, no solo para un proyecto específico. |
| `require`           | Agrega e instala una dependencia.                                               |
| `laravel/installer` | Paquete que proporciona el comando `laravel`.                                   |

## Configuración del PATH de Composer

En algunos sistemas, después de instalar el instalador de Laravel, el comando `laravel` puede no estar disponible directamente. Para resolverlo, se agrega el directorio global de Composer al `PATH`.

Para Bash:

```bash
echo 'export PATH="$HOME/.config/composer/vendor/bin:$HOME/.composer/vendor/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

Para Zsh:

```bash
echo 'export PATH="$HOME/.config/composer/vendor/bin:$HOME/.composer/vendor/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

| Parte       | Descripción                                            |
| ----------- | ------------------------------------------------------ |
| `echo`      | Imprime texto en la terminal.                          |
| `>>`        | Agrega el texto al final del fichero indicado.         |
| `~/.bashrc` | Fichero de configuración de Bash.                      |
| `~/.zshrc`  | Fichero de configuración de Zsh.                       |
| `source`    | Recarga la configuración de la shell actual.           |
| `PATH`      | Variable que indica dónde buscar comandos ejecutables. |

## Creación del proyecto Laravel

Desde la raíz del proyecto HojasTech se creó el directorio del backend:

```bash
mkdir -p backend
cd backend
```

Después se inició el proyecto Laravel:

```bash
laravel new api
cd api
```

| Comando            | Descripción                                      |
| ------------------ | ------------------------------------------------ |
| `mkdir -p backend` | Crea el directorio `backend` si no existe.       |
| `cd backend`       | Entra al directorio `backend`.                   |
| `laravel new api`  | Crea una nueva aplicación Laravel llamada `api`. |
| `cd api`           | Entra al proyecto Laravel creado.                |

## Opciones seleccionadas durante la instalación

Durante el asistente interactivo de Laravel se seleccionaron estas opciones:

| Pregunta          | Respuesta seleccionada | Motivo                                                                    |
| ----------------- | ---------------------- | ------------------------------------------------------------------------- |
| Starter kit       | `None`                 | Se busca un backend API limpio y separado del frontend.                   |
| Testing framework | `PHPUnit`              | Se eligió por familiaridad con programación orientada a objetos.          |
| Laravel Boost     | `Yes`                  | Quedó seleccionado durante la instalación como herramienta de desarrollo. |
| Database          | `PostgreSQL`           | PostgreSQL es la base de datos propuesta para HojasTech.                  |
| Run migrations    | `No`                   | Faltaba validar PostgreSQL y la extensión PDO de PostgreSQL para PHP.     |

## Sobre PHPUnit

Se eligió **PHPUnit** como framework de pruebas porque trabaja con una estructura clásica orientada a objetos.

Ejemplo de prueba con PHPUnit en Laravel:

```php
public function test_example(): void
{
    $response = $this->get('/');

    $response->assertStatus(200);
}
```

PHPUnit permite organizar las pruebas mediante clases, métodos y assertions. Esto es adecuado para mantener pruebas claras en el backend.

## Sobre Laravel Boost

Laravel Boost fue seleccionado durante la instalación.

Boost es una dependencia de desarrollo orientada a mejorar el trabajo con asistentes de IA en proyectos Laravel. No debe considerarse una dependencia funcional del sistema agrícola HojasTech.

Para verificar si Laravel Boost quedó instalado:

```bash
composer show laravel/boost
```

Si el paquete está instalado, Composer mostrará información del paquete.

Si no está instalado, Composer mostrará un error indicando que no encontró el paquete.

## Problema detectado con PostgreSQL

Durante la instalación apareció un mensaje similar a:

```bash
PostgreSQL (Missing PDO extension)
```

Esto significa que Laravel detectó PostgreSQL como base de datos, pero PHP en el host todavía no tenía habilitada la extensión necesaria para conectarse a PostgreSQL mediante PDO.

PDO significa **PHP Data Objects**. Es una capa de acceso a bases de datos usada por PHP y por Laravel para comunicarse con motores como PostgreSQL.

## Instalación de soporte PostgreSQL para PHP

En CachyOS/Arch Linux, el soporte de PostgreSQL para PHP se instaló con:

```bash
sudo pacman -S php-pgsql
```

Este paquete proporciona los módulos necesarios para que PHP pueda conectarse a PostgreSQL.

Módulos esperados:

```bash
pdo_pgsql
pgsql
```

| Módulo      | Propósito                                    |
| ----------- | -------------------------------------------- |
| `pdo_pgsql` | Driver PDO para conectar PHP con PostgreSQL. |
| `pgsql`     | Extensión nativa de PHP para PostgreSQL.     |

## Configuración de php.ini en el host

El fichero principal de configuración de PHP en el host es:

```bash
/etc/php/php.ini
```

Esto fue verificado con:

```bash
php --ini
```

La salida confirmó:

```bash
Configuration File (php.ini) Path: "/etc/php"
Loaded Configuration File:         "/etc/php/php.ini"
Scan for additional .ini files in: "/etc/php/conf.d"
Additional .ini files parsed:      (none)
```

Antes de editar el fichero, es recomendable crear una copia de seguridad:

```bash
sudo cp /etc/php/php.ini /etc/php/php.ini.bak
```

Luego se abre el fichero:

```bash
sudo nvim /etc/php/php.ini
```

Dentro de `php.ini`, se deben habilitar estas extensiones:

```ini
extension=pdo_pgsql
extension=pgsql
```

Si aparecen comentadas así:

```ini
;extension=pdo_pgsql
;extension=pgsql
```

se debe quitar el punto y coma inicial.

En `php.ini`, el carácter `;` al inicio de una línea significa que esa línea está comentada y no se ejecuta.

## Verificación de extensiones PHP

Después de instalar `php-pgsql` y editar `php.ini`, se verificó que PHP detecta las extensiones:

```bash
php -m | grep -E "pdo_pgsql|pgsql"
```

La salida obtenida fue:

```bash
pdo_pgsql
pgsql
```

Esto confirma que PHP ya puede conectarse a PostgreSQL mediante PDO.

También se puede revisar cada extensión por separado:

```bash
php --ri pdo_pgsql
php --ri pgsql
```

Si las extensiones están activas, PHP mostrará información técnica de cada módulo.

## Entorno host verificado

El entorno local actual es:

| Herramienta         | Versión detectada |
| ------------------- | ----------------- |
| Sistema operativo   | CachyOS Linux     |
| Base del sistema    | Arch Linux        |
| Kernel              | 7.0.9-1-cachyos   |
| PHP                 | 8.5.7             |
| Composer            | 2.10.1            |
| Node.js             | 26.2.0            |
| npm                 | 11.13.0           |
| Git                 | 2.54.0            |
| Laravel Installer   | 5.28.1            |
| Paquete `php`       | 8.5.7-1           |
| Paquete `composer`  | 2.10.1-1          |
| Paquete `nodejs`    | 26.1.0-2          |
| Paquete `npm`       | 11.16.0-1         |
| Paquete `git`       | 2.54.0-1.1        |
| Paquete `unzip`     | 6.0-23.1          |
| Paquete `php-pgsql` | 8.5.7-1           |

Nota: la versión mostrada por `node -v` y la versión del paquete `nodejs` reportada por `pacman -Q` pueden diferir ligeramente si el sistema tiene actualizaciones recientes o si la terminal conserva una sesión anterior. Para confirmar la versión efectiva usada por la terminal, usar siempre:

```bash
node -v
which node
pacman -Q nodejs
```

## Comandos usados para verificar versiones

Para documentar el entorno local se ejecutaron estos comandos:

```bash
cat /etc/os-release
uname -r
php -v
composer -V
node -v
npm -v
git --version
laravel --version
pacman -Q php composer nodejs npm git unzip php-pgsql
php --ini
php -m | grep -E "pdo_pgsql|pgsql"
```

| Comando               | Información que muestra                            |
| --------------------- | -------------------------------------------------- |
| `cat /etc/os-release` | Información de la distribución Linux.              |
| `uname -r`            | Versión del kernel Linux.                          |
| `php -v`              | Versión instalada de PHP.                          |
| `composer -V`         | Versión instalada de Composer.                     |
| `node -v`             | Versión efectiva de Node.js en la terminal.        |
| `npm -v`              | Versión efectiva de npm en la terminal.            |
| `git --version`       | Versión instalada de Git.                          |
| `laravel --version`   | Versión del instalador de Laravel.                 |
| `pacman -Q`           | Versiones de paquetes instalados desde el sistema. |
| `php --ini`           | Fichero `php.ini` cargado por PHP.                 |
| `php -m`              | Módulos cargados por PHP.                          |

## Verificar paquetes instalados con pacman

Para comprobar los paquetes instalados desde el sistema:

```bash
pacman -Q php composer nodejs npm git unzip php-pgsql
```

Este comando muestra la versión instalada de cada paquete.

Si un paquete no está instalado, `pacman` mostrará un mensaje indicando que no fue encontrado.

## Verificar dependencias de Composer

Para revisar las dependencias del proyecto Laravel:

```bash
composer show
```

Para verificar el instalador global de Laravel:

```bash
composer global show laravel/installer
```

Para verificar Laravel Boost dentro del proyecto:

```bash
composer show laravel/boost
```

## Verificar Laravel

Desde el directorio del proyecto:

```bash
cd backend/api
php artisan --version
```

Este comando muestra la versión del framework Laravel instalada en el proyecto.

También se puede revisar el estado general de configuración:

```bash
php artisan about
```

## Configuración de base de datos en .env

Laravel usa el fichero `.env` para definir variables de entorno.

El fichero se encuentra en:

```bash
backend/api/.env
```

Para abrirlo:

```bash
code backend/api/.env
```

O con Neovim:

```bash
nvim backend/api/.env
```

La configuración propuesta para PostgreSQL cuando se ejecute desde el host es:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hojastech
DB_USERNAME=hojastech
DB_PASSWORD=replace_with_secure_password
```

Cuando se ejecute con Docker Compose, el host de base de datos normalmente será el nombre del servicio de PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hojastech
DB_USERNAME=hojastech
DB_PASSWORD=replace_with_secure_password
```

El valor exacto dependerá del nombre final del servicio definido en `docker/dev/docker-compose.dev.yml`.

El fichero `.env` no debe subirse al repositorio porque puede contener credenciales sensibles.

## Verificar conexión a base de datos

Cuando PostgreSQL esté instalado o levantado con Docker, y la base de datos exista, ejecutar:

```bash
php artisan migrate
```

Si la conexión es correcta, Laravel ejecutará las migraciones.

Si hay un error de conexión, revisar:

```bash
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

También revisar que las extensiones estén activas:

```bash
php -m | grep -E "pdo_pgsql|pgsql"
```

## Relación con Docker

Aunque en esta etapa se configuró PHP en el host, HojasTech usará Docker para evitar depender exclusivamente de la configuración local de una máquina.

La configuración del host sirve para:

1. Crear el proyecto Laravel.
2. Ejecutar comandos rápidos de Artisan.
3. Verificar dependencias locales.
4. Depurar errores iniciales.
5. Trabajar temporalmente antes de completar Docker.

La configuración Docker debe permitir levantar servicios como:

```bash
app
postgres
redis
```

Posibles servicios futuros:

```bash
caddy
minio
mailpit
queue
scheduler
```

La configuración Docker debe ubicarse en:

```bash
docker/dev
```

Y no debe mezclarse con herramientas auxiliares ubicadas en:

```bash
docker/tools
```

## Diferencia entre host y Docker

| Entorno           | Uso                                                            |
| ----------------- | -------------------------------------------------------------- |
| Host CachyOS/Arch | Desarrollo local temporal, creación inicial, comandos rápidos. |
| Docker            | Entorno reproducible para servicios del proyecto.              |

Cuando Docker esté configurado, el contenedor de Laravel deberá incluir las extensiones necesarias para PostgreSQL.

En una imagen PHP basada en Debian, normalmente se requiere instalar soporte PostgreSQL y habilitar extensiones como:

```dockerfile
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql
```

En una imagen PHP basada en Alpine, normalmente se requiere algo parecido a:

```dockerfile
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pgsql
```

La opción final debe definirse cuando se cree el `Dockerfile` real del backend.

## Comandos principales de desarrollo

Desde el directorio:

```bash
backend/api
```

Instalar dependencias PHP:

```bash
composer install
```

Instalar dependencias Node:

```bash
npm install
```

Compilar recursos frontend:

```bash
npm run build
```

Levantar entorno de desarrollo de Laravel desde el host:

```bash
composer run dev
```

Ejecutar servidor Laravel manualmente:

```bash
php artisan serve
```

Ejecutar migraciones:

```bash
php artisan migrate
```

Ejecutar pruebas con PHPUnit:

```bash
php artisan test
```

## Comandos de diagnóstico

Verificar configuración cargada de PHP:

```bash
php --ini
```

Verificar módulos cargados:

```bash
php -m
```

Buscar módulos PostgreSQL:

```bash
php -m | grep -E "pdo_pgsql|pgsql"
```

Verificar versión de Laravel:

```bash
php artisan --version
```

Verificar configuración general de Laravel:

```bash
php artisan about
```

Verificar estado de Git:

```bash
git status
```

## Ficheros importantes del backend

| Fichero o directorio | Propósito                                                  |
| -------------------- | ---------------------------------------------------------- |
| `app/`               | Código principal de la aplicación Laravel.                 |
| `bootstrap/`         | Arranque interno del framework.                            |
| `config/`            | Configuración del proyecto Laravel.                        |
| `database/`          | Migraciones, factories y seeders.                          |
| `public/`            | Punto de entrada público de la aplicación.                 |
| `resources/`         | Vistas, estilos y recursos frontend generados por Laravel. |
| `routes/`            | Definición de rutas.                                       |
| `storage/`           | Logs, cachés y ficheros generados.                         |
| `tests/`             | Pruebas automatizadas.                                     |
| `.env`               | Variables de entorno locales.                              |
| `.env.example`       | Plantilla segura de variables de entorno.                  |
| `composer.json`      | Dependencias y scripts PHP.                                |
| `package.json`       | Dependencias y scripts Node.                               |

## Nota sobre resources y frontend

Aunque Laravel incluye el directorio `resources/`, en HojasTech no se usará inicialmente como frontend principal.

El frontend web principal debe crearse después en:

```bash
frontend/web
```

El directorio `resources/` puede quedar para recursos internos generados por Laravel o para necesidades puntuales del backend, pero no debe confundirse con el frontend principal del sistema.

## Seguridad inicial

El fichero `.env` no debe subirse al repositorio.

Verificar que `.env` esté ignorado:

```bash
git status --ignored
```

Laravel normalmente ya incluye `.env` en `.gitignore`.

También se debe evitar subir credenciales reales de:

```bash
DB_PASSWORD
APP_KEY
API_KEYS
TOKENS
SECRETS
```

## Estado pendiente

Tareas pendientes recomendadas:

| Prioridad | Tarea                                                                            |
| --------- | -------------------------------------------------------------------------------- |
| `p0`      | Crear base de datos PostgreSQL para desarrollo o levantar PostgreSQL con Docker. |
| `p0`      | Ejecutar `php artisan migrate`.                                                  |
| `p0`      | Crear configuración Docker para backend, PostgreSQL y Redis.                     |
| `p1`      | Crear `frontend/web` con React cuando se confirme el enfoque del frontend.       |
| `p1`      | Actualizar `docs/technologies.md`.                                               |
| `p1`      | Actualizar `docs/architecture.md`.                                               |
| `p1`      | Registrar avance en `docs/worklog.md`.                                           |
| `p1`      | Registrar cambio en `docs/changelog.md`.                                         |
| `p2`      | Documentar comandos Docker definitivos.                                          |
| `p2`      | Registrar una decisión técnica sobre backend API y frontend separado.            |

## Tareas recomendadas para Vikunja

Tareas sugeridas:

| Prioridad | Tarea                                                      | Etiquetas                             |
| --------- | ---------------------------------------------------------- | ------------------------------------- |
| `p0`      | Configurar Docker Compose para Laravel, PostgreSQL y Redis | `p0`, `docker`, `backend`, `database` |
| `p0`      | Ejecutar migraciones iniciales de Laravel                  | `p0`, `backend`, `database`           |
| `p1`      | Crear proyecto React separado en `frontend/web`            | `p1`, `frontend`                      |
| `p1`      | Documentar arquitectura backend API y frontend separado    | `p1`, `docs`, `architecture`          |
| `p2`      | Preparar pruebas iniciales con PHPUnit                     | `p2`, `testing`, `backend`            |

## Documentación relacionada que debe actualizarse

Cuando se confirme esta estructura, revisar estos ficheros:

| Fichero                       | Motivo                                                                                     |
| ----------------------------- | ------------------------------------------------------------------------------------------ |
| `docs/architecture.md`        | Documentar separación entre backend API y frontend web.                                    |
| `docs/technologies.md`        | Registrar Laravel, PostgreSQL, React y Docker como tecnologías propuestas o en evaluación. |
| `docs/technical-decisions.md` | Registrar decisión sobre no usar React Starter Kit y mantener frontend separado.           |
| `docs/worklog.md`             | Registrar el avance de inicialización del backend.                                         |
| `docs/changelog.md`           | Registrar cambio relevante en la estructura del proyecto.                                  |

## Comandos recomendados para Git

Desde la raíz de HojasTech:

```bash
git status
```

Agregar cambios relacionados con el backend:

```bash
git add backend/api
```

Agregar documentación si fue actualizada:

```bash
git add docs
```

Crear commit:

```bash
git commit -m "Initialize Laravel backend"
```

Si también se actualiza documentación de arquitectura o decisiones técnicas, puede usarse un commit más específico:

```bash
git commit -m "Document Laravel backend setup"
```

## Resumen

En esta etapa se inició el backend de HojasTech con Laravel dentro de `backend/api`.

Se seleccionó una instalación limpia sin starter kit porque HojasTech busca mantener Laravel como backend API y crear React como frontend separado. Se eligió PHPUnit por familiaridad con programación orientada a objetos, PostgreSQL como base de datos propuesta y Laravel Boost como herramienta de desarrollo.

También se instaló soporte PostgreSQL para PHP mediante `php-pgsql`, se verificó el fichero `/etc/php/php.ini` del host y se confirmó que las extensiones `pdo_pgsql` y `pgsql` están activas.

La configuración actual del host es útil para avanzar, pero el proyecto debe migrar progresivamente hacia un entorno Docker reproducible para evitar depender de configuraciones locales específicas.
