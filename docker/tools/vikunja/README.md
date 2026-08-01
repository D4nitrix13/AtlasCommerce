# Vikunja para la Gestión del Proyecto HojasTech

## Propósito

Este directorio contiene la configuración de **Vikunja**, una herramienta self-hosted utilizada para planificar, organizar y dar seguimiento al desarrollo del proyecto **HojasTech**.

Vikunja no forma parte funcional del sistema agrícola HojasTech. Su propósito es servir como herramienta interna de gestión técnica para administrar tareas, fases de desarrollo, prioridades, avances, bloqueos, documentación pendiente y actividades futuras del proyecto.

## Contexto del proyecto

HojasTech será una plataforma web y móvil orientada a la asistencia agrícola. Su alcance puede incluir gestión de cultivos, vademécum digital, cálculo de dosis, registro de labores, diagnóstico por inteligencia artificial, geolocalización de puntos de venta, reportes, monitoreo de rendimiento y futura aplicación móvil.

Las tecnologías del proyecto todavía pueden cambiar. Por esa razón, la documentación debe mantenerse como una referencia evolutiva y no como una definición rígida del stack final.

## Separación de servicios y herramientas

La estructura del proyecto diferencia entre servicios funcionales y herramientas auxiliares.

```bash
docker/dev/
```

Este directorio está reservado para los servicios reales del sistema HojasTech, como backend, frontend, base de datos, cache, reverse proxy, storage, servicios de IA o monitoreo.

```bash
docker/tools/vikunja/
```

Este directorio contiene Vikunja como herramienta auxiliar para administrar tareas, prioridades, planificación y seguimiento del desarrollo.

Esta separación permite mantener una arquitectura más limpia, evitar dependencias innecesarias en el entorno principal y reducir el riesgo de mezclar herramientas de gestión con servicios productivos.

## Estructura del directorio

```bash
vikunja/
├── docker-compose.yml
├── .env
├── .env.example
├── .gitignore
├── README.md
├── backup-vikunja.sh
├── files/
├── db/
├── tmp/
└── backups/
```

## Elementos principales

| Elemento             | Descripción                                                                                                   |
| -------------------- | ------------------------------------------------------------------------------------------------------------- |
| `docker-compose.yml` | Define los servicios necesarios para ejecutar Vikunja y su base de datos PostgreSQL.                          |
| `.env`               | Contiene variables sensibles del entorno local, como credenciales y secretos. No debe subirse al repositorio. |
| `.env.example`       | Plantilla pública de variables de entorno para documentar la configuración requerida.                         |
| `.gitignore`         | Evita subir ficheros sensibles, datos persistentes y respaldos al repositorio.                                |
| `README.md`          | Documenta el propósito, uso y mantenimiento de esta herramienta.                                              |
| `backup-vikunja.sh`  | Script auxiliar para generar respaldos portátiles de Vikunja.                                                 |
| `files/`             | Almacena ficheros adjuntos subidos desde Vikunja.                                                             |
| `db/`                | Contiene los datos persistentes de PostgreSQL para Vikunja.                                                   |
| `tmp/`               | Directorio temporal usado por el contenedor.                                                                  |
| `backups/`           | Directorio local donde se guardan respaldos exportables.                                                      |

## Configuración inicial

Antes de levantar Vikunja, crear el fichero `.env` a partir de `.env.example`.

```bash
cd docker/tools/vikunja
cp .env.example .env
```

Abrir el fichero `.env` para configurar los valores reales.

```bash
code .env
```

Con Neovim:

```bash
nvim .env
```

Para generar una contraseña segura para PostgreSQL, usar:

```bash
openssl rand -base64 32
```

Para generar un secreto seguro para Vikunja, usar:

```bash
openssl rand -hex 64
```

Estos valores deben colocarse únicamente en `.env`. El fichero `.env.example` debe conservar valores de ejemplo y puede subirse al repositorio.

## Levantar Vikunja

Desde la raíz del proyecto, entrar al directorio de Vikunja.

```bash
cd docker/tools/vikunja
```

Levantar los contenedores.

```bash
docker compose up -d
```

Verificar que los servicios estén activos.

```bash
docker compose ps
```

Revisar logs si es necesario.

```bash
docker compose logs -f
```

## Acceso a la interfaz

Una vez levantado el servicio, abrir Vikunja desde el navegador.

```bash
http://localhost:3456
```

El primer usuario debe crearse desde la interfaz web.

Después de crear la cuenta principal, se recomienda mantener desactivado el registro público mediante la siguiente variable en `.env`.

```env
VIKUNJA_SERVICE_ENABLEREGISTRATION=false
```

## Gestión del servicio

Para detener Vikunja sin eliminar datos persistentes, ejecutar:

```bash
docker compose down
```

Este comando detiene los contenedores, pero conserva los datos locales almacenados en `db/`, `files/`, `tmp/` y `backups/`.

Para revisar únicamente los logs de Vikunja:

```bash
docker compose logs -f vikunja
```

Para revisar únicamente los logs de PostgreSQL:

```bash
docker compose logs -f db
```

## Backup y restauración

Para crear un respaldo exportable, ejecutar:

```bash
./backup-vikunja.sh
```

El respaldo generado se almacenará dentro de:

```bash
backups/
```

El backup debe incluir la base de datos exportada y los ficheros necesarios para restaurar el estado de Vikunja en otra máquina o después de una reinstalación.

Para una restauración general se deben conservar como mínimo:

```bash
docker-compose.yml
.env.example
files/
backup de PostgreSQL
```

El directorio `db/` sirve para persistencia local durante el uso diario, pero no debe ser el único mecanismo de protección. Para portabilidad y seguridad, se deben generar respaldos exportables mediante `backup-vikunja.sh`.

## Uso recomendado en HojasTech

Dentro de Vikunja se recomienda crear un proyecto llamado:

```bash
HojasTech
```

Columnas iniciales recomendadas:

```bash
Pendiente
En Progreso
Terminado
```

Cuando el flujo de trabajo crezca, se pueden agregar columnas adicionales:

```bash
Ideas
Esta Semana
Hoy
Probando
Bloqueado
```

## Etiquetas y prioridades

Etiquetas técnicas iniciales:

```bash
p0
p1
p2
p3
backend
frontend
database
docker
postgresql
redis
caddy
minio
ia
mobile
nativephp
performance
security
testing
docs
setup
monitoring
```

Criterio de prioridades:

| Etiqueta | Significado                                             |
| -------- | ------------------------------------------------------- |
| `p0`     | Tarea obligatoria para que el proyecto pueda avanzar.   |
| `p1`     | Tarea importante para el MVP.                           |
| `p2`     | Mejora profesional o técnica que fortalece el proyecto. |
| `p3`     | Funcionalidad avanzada, experimental o futura.          |

## Flujo diario

El flujo mínimo recomendado es:

```bash
Pendiente → En Progreso → Terminado
```

Cada día se debe seleccionar una tarea pequeña, moverla a `En Progreso`, trabajar en ella y luego moverla a `Terminado` si fue completada.

Si la tarea no se completa, se debe dejar un comentario con esta estructura:

```bash
Avance realizado
Problema encontrado
Próximo paso recomendado
```

## Reglas de uso

1. No crear tareas demasiado grandes.
2. Dividir cada módulo en actividades pequeñas y medibles.
3. Usar etiquetas técnicas para clasificar el trabajo.
4. Usar prioridades `p0`, `p1`, `p2` y `p3` para ordenar el avance.
5. Registrar bloqueos con comentarios claros.
6. No mezclar tareas personales con tareas técnicas del proyecto.
7. Generar backups periódicos.
8. No subir `.env`, `db/`, `files/`, `tmp/` ni `backups/` al repositorio.
9. Actualizar la documentación cuando cambien tecnologías, servicios, directorios o decisiones técnicas.

## Ficheros excluidos del repositorio

Los siguientes elementos no deben subirse al repositorio:

```bash
.env
db/
files/
tmp/
backups/
```

Estos elementos contienen datos locales, ficheros persistentes, credenciales o respaldos que no deben formar parte del control de versiones.

## Relación con la documentación principal

Vikunja debe mantenerse alineado con los siguientes ficheros de documentación:

| Fichero                       | Propósito                                                   |
| ----------------------------- | ----------------------------------------------------------- |
| `docs/architecture.md`        | Describe la arquitectura general y su evolución.            |
| `docs/technologies.md`        | Registra tecnologías propuestas, evaluadas o seleccionadas. |
| `docs/technical-decisions.md` | Documenta decisiones técnicas importantes.                  |
| `docs/roadmap.md`             | Define fases y prioridades del proyecto.                    |
| `docs/worklog.md`             | Registra avances de trabajo.                                |
| `docs/changelog.md`           | Resume cambios relevantes del proyecto.                     |

Cuando una tarea en Vikunja represente un cambio importante, debe revisarse si también corresponde actualizar alguno de estos ficheros.

## Resumen

Vikunja se utiliza en HojasTech como una herramienta auxiliar para mantener control sobre el avance del proyecto, organizar tareas, priorizar entregables y documentar bloqueos técnicos.

Mantener Vikunja separado del entorno principal permite conservar una arquitectura ordenada, distinguir claramente entre servicios funcionales y herramientas de gestión, y facilitar el mantenimiento del proyecto a largo plazo.
