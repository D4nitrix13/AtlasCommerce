<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: docker-stack-orchestration
description: Multi-instance Docker orchestration (3 prod + 3 dev), Caddy + per-tenant IPs, volumes, env per instance. Required reading before editing ANY Dockerfile, docker-compose.yml, entrypoint.sh, conf/php, conf/postgres, conf/redis, conf/nginx, or .env under docker/instances/. Enforces: absolute paths for ALL binaries (RUN/CMD/ENTRYPOINT/command:/shell), no-root users, tini as PID 1, separate networks per instance, host UID binding for dev bind-mounts. Triggers: adding a new tenant, debugging docker-compose, env files, Dockerfile, entrypoint, scripts/docker-stack.sh, docker/instances/, paths absolutos, PATH hijacking, security hardening.
---

# Docker Stack Orchestration

## Topology

The monorepo runs **6 instances** in parallel (3 prod + 3 dev), all via
`docker compose v2`:

| Slug | Prod network | Dev bind mount |
|---|---|---|
| `panda-estampados` + `kitsune` | `panda-estampados` (172.21.0.0/24) | `panda-estampados-dev` (5321) |
| `mm-boutique` | `mm-boutique` (172.22.0.0/24) | `mm-boutique-dev` (6321) |
| `morales-store` | `morales-store` (172.23.0.0/24) | `morales-store-dev` (7321) |

**Prod** uses fixed IPs on bridge networks (`app=172.X.0.20:8000`,
`frontend=172.X.0.21:4321`).
**Dev** uses bind mounts + xdebug, ports published on the host
(frontend 5321/6321/7321, avoiding conflict between themselves and
with prod).

## Hard rules (non-negotiable, applies to every file under `docker/`)

These are not preferences. They are documented in
[docker/COMPOSE.md](../COMPOSE.md) and
[docker/DOCKERFILES.md](../DOCKERFILES.md) but restated HERE so any
agent that loads ONLY this skill has the rules without opening other
files.

### Always use absolute paths for binaries

Any binary invoked from `RUN`, `CMD`, `ENTRYPOINT`, `command:` (compose),
or from inside an `entrypoint.sh` MUST be invoked with its absolute path.
No `php artisan ...` — must be `/usr/local/bin/php artisan ...`.

**Why**: PATH hijacking. If a writable dir is on `$PATH` (typical in
containers with `/app/storage` or temp dirs), a malicious binary there
intercepts the call. Absolute path = no interception.

**Common binaries in this repo** (full table in `docker/COMPOSE.md#1`):

| Binary                     | Path                                              |
|----------------------------|---------------------------------------------------|
| `corepack`                 | `/usr/local/bin/corepack`                         |
| `pnpm`                     | `/usr/local/bin/pnpm`                             |
| `node`                     | `/usr/local/bin/node`                             |
| `php`                      | `/usr/local/bin/php`                              |
| `php-fpm`                  | `/usr/local/sbin/php-fpm`                         |
| `composer`                 | `/usr/bin/composer`                               |
| `docker-php-ext-install`   | `/usr/local/bin/docker-php-ext-install`           |
| `docker-php-ext-enable`    | `/usr/local/bin/docker-php-ext-enable`            |
| `pecl`                     | `/usr/local/bin/pecl`                             |
| `apk`                      | `/sbin/apk`                                       |
| `postgres`                 | `/usr/lib/postgresql/<ver>/bin/postgres`          |
| `redis-server`             | `/usr/local/bin/redis-server`                     |
| `pg_isready`               | `/usr/bin/pg_isready`                             |
| `id`                       | `/usr/bin/id`                                     |
| `grep`                     | `/usr/bin/grep`                                   |
| `seq`                      | `/usr/bin/seq`                                    |
| `sleep`                    | `/bin/sleep`                                      |
| `wget`                     | `/usr/bin/wget`                                   |
| `chown` / `chmod` / `mkdir`| `/bin/chown` / `/bin/chmod` / `/bin/mkdir`        |
| `cp` / `rm` / `pwd`        | `/usr/bin/cp` / `/bin/rm` / `/usr/bin/pwd`        |

If a binary you need is NOT in the table, run
`docker compose exec <svc> /usr/bin/which <name>` to find its real
path. Never assume `name` works.

### Other hard rules

- **No-root runtime**: container runs as `www-data` (UID 33) for backend
  or `node` (UID 1000) for frontend. `root` only in build time.
- **`init: true`** on every service we author (not needed for official
  images — they bring their own PID 1).
- **No `docker run` loose** — always via compose.
- **One `docker-compose.yml` per instance** under `docker/instances/<stage>/<store>/`.
  No sharing of volumes or networks between prod and dev.
- **`depends_on` with `condition: service_healthy`** for every dependency
  that exposes a port (postgres, redis, etc.).

## File layout

```text
docker/
├── versions.env ← pinned versions (PHP, Node, Postgres, etc.)
├── versions.env.example
├── COMPOSE.md ← orchestration documentation
├── DOCKERFILES.md ← Dockerfile conventions
├── instances/
│ ├── development/
│ │ ├── panda-estampados/ ← dev compose + env
│ │ ├── mm-boutique/
│ │ └── morales-store/
│ └── production/
│ ├── (one per store)
├── database/ ← init Postgres per tenant
├── backend/ ← Laravel backend Dockerfile
└── frontend/ ← Astro frontend Dockerfile

scripts/
├── docker-stack.sh ← entry point of orchestrator
├── docker/_lib/ ← shared lib (colors, logging, resolve, compose)
└── docker/{up,start,stop,...}/ ← one action per folder
```text

## Key commands

```bash
# Start prod
./scripts/docker-stack.sh start production

# Start dev
./scripts/docker-stack.sh start development

# Stop dev
./scripts/docker-stack.sh stop development

# Help
./scripts/docker-stack.sh help
```text

## Conventions

- **Each `docker/instances/<env>/<name>/` has its own `docker-compose.yml`

- `.env`**. DO NOT share between instances.

- **Common env vars** → `docker/versions.env` (pinned). Per-instance vars
 → `.env` of that folder.
- **Postgres init scripts** → `docker/database/init/` + each store has
 its own DB (`CREATE DATABASE ...`).
- **`docker-stack.sh` actions** are independent files in
 `scripts/docker/<action>/<action>.sh`. Shared lib is in
 `scripts/docker/_lib/`.
- **DO NOT use `docker run` loose** - always via compose.

## Trigger patterns

Activate when:

- Creating a new store → 4 files: `docker/instances/production/<slug>/docker-compose.yml`

- `.env`, `docker/instances/development/<slug>/...`, plus block in `Caddyfile`,
 plus theme package in `frontend/packages/themes/`, plus slug in `Caddyfile` domains.

- Modifying `versions.env` (bump of PHP/Node/Postgres).
- Editing `docker-stack.sh` (new command).
- Creating new script in `scripts/docker/<action>/`.
- Debugging "instance doesn't start" → check `<env>/<name>/.env` first,
 then `docker compose logs -f`.

## Adding a new tenant (end-to-end)

1. **Frontend theme**: `frontend/packages/themes/<slug>/src/index.ts` + symlink.
2. **Frontend config**: `frontend/src/config/stores.ts::STORE_BRANDS`.
3. **Backend** (no direct changes - the tenant is configured per `store_domains` row in seeder).
4. **Docker prod**: `docker/instances/production/<slug>/docker-compose.yml` + `.env`.
5. **Docker dev**: `docker/instances/development/<slug>/docker-compose.yml` + `.env`.
6. **Postgres init**: add `docker/database/init/<slug>-db.sql`.
7. **Caddyfile**: block for `<slug>` in `panda-estampados` or new network.
8. **Seeder**: `tests/Feature/Console/DefaultAdminSeederTest.php` to validate the bootstrap.

## Anti-patterns

- ❌ Touching `docker-compose.yml` in `docker/` (root) - doesn't exist, it's per instance.
- ❌ Sharing volumes between prod and dev instances.
- ❌ Hardcoded IPs outside the 172.20-23 range (Caddy resolves with these networks).
- ❌ Bash scripts > 100 lines - split into `scripts/docker/<action>/<action>.sh`.
- ❌ Commands without `set -euo pipefail`.

## Related skills

- `caddyfile-multi-tenant-routing` - host → backend/frontend net.
- `astro-themes-and-palette` - frontend branding.
- `laravel-multi-tenant-patterns` - backend tenant resolution.
