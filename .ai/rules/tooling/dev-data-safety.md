# Seguridad de datos del entorno dev — BD de test vs BD real

> Regla load-bearing para cualquier operación con la base de datos del
> dev: tests, migraciones, seeds, wipes. Motivada por la pérdida real de
> datos en este repo ("perdimos los datos tres veces", documentado en
> `backend/phpunit.xml`): `RefreshDatabase` apuntó a la BD de desarrollo
> porque la variable del container ganó al `<env>` de PHPUnit.

## Las dos bases, sin excepciones

| Base | Uso | Nombre (instance `panda-estampados-and-kitsune`) |
|---|---|---|
| `_test` | SOLO tests (`RefreshDatabase` trunca) | `panda-estampados-and-kitsune_test` |
| dev | datos reales del desarrollo | `panda-estampados_and_kitsune` (ver `backend/.env`) |

- `backend/tests/bootstrap.php` **aborta** si la BD no termina en `_test`
  — no saltar ese guard, no renombrar la BD de test para "que pase".
- `phpunit.xml` fija `DB_DATABASE` con `force="false"`: la variable ya
  exportada por el entorno **gana**. Por eso los tests corren dentro del
  container (`scripts/docker-stack.sh exec …`) y no desde el host.
- `backend/.env` y `backend/.env.testing` deben apuntar a su BD
  correspondiente. Al recrear un instance, verificar esta línea (una vez
  el `.env` quedó apuntando a la BD de OTRO tenant).

## Operaciones destructivas

- **Nunca** `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`
  masivo ni `RefreshDatabase` manual contra la BD de desarrollo.
- Antes de cualquier operación destructiva aprobada por el usuario:
  **dump primero** (`pg_dump` vía `scripts/docker-stack.sh exec … postgres
  pg_dump -U <user> <db> > backup_$(date +%F).sql`), confirmar el archivo
  y recién ahí operar.
- Un worktree nuevo NO trae `.env` (gitignored): copiar `backend/.env`,
  `backend/.env.testing` y `docker/versions.env` +
  `docker/instances/.../environment/.env` del checkout principal antes de
  levantar stack o correr tests. Sin `APP_KEY` (que vive en
  `backend/.env`) todo el auth muere.

## Locks y limpieza

- Interrumpir tests con `RefreshDatabase` deja transacciones abortadas
  que bloquean la corrida siguiente (`alter table`/`drop table` colgados):
  `select pg_terminate_backend(pid) from pg_stat_activity where
  datname='<bd_test>' and pid <> pg_backend_pid();`
- Si los tests se cuelgan tras una corrida cancelada, limpiar locks ANTES
  de reintentar; no matar el container entero.
