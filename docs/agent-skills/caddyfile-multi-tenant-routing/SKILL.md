---
name: caddyfile-multi-tenant-routing
description: Reverse proxy + multi-tenant routing in Caddyfile. Hosts per store, fixed IPs for prod, bind mounts for dev, /api routing. Triggers: editing Caddyfile, adding domain, modifying production/dev routing.
---

# Caddyfile Multi-Tenant Routing

## Overview

Caddy (not Nginx) runs as reverse proxy on the host. Resolves domains
to the correct Docker instances according to `panda-estampados.com`,
`mm-boutique.com`, etc.

```caddyfile
{
 auto_https off
 admin off
}
```

## Canonical blocks

### Prod (fixed IPs on bridge networks)

```caddyfile
# panda-estampados
*.panda-estampados.com, panda-estampados.com {
 reverse_proxy 172.21.0.20:8000 172.21.0.21:4321
}
*.kitsune.com, kitsune.com {
 reverse_proxy 172.21.0.20:8000 172.21.0.21:4321
}
```

### Dev (bind mounts on host)

```caddyfile
# panda-estampados-dev
@dev-panda host panda-estampados.test localhost
handle @dev-panda {
 reverse_proxy localhost:5321
}
```

## Rules

- `*.panda-estampados.com` → backend (172.21.0.20:8000) + frontend (172.21.0.21:4321).
- `/api/*` on any host → backend of the corresponding instance.
- Any other host → 404.
- **Dev is activated** with `./scripts/docker-stack.sh start development`.

## Conventions

- **DO NOT** use `nginx` - the project uses Caddy.
- **DO NOT** expose Laravel/Astro ports to the host in prod - only internal IPs.
- **Dev uses localhost** (not internal IPs) so the host resolves without Docker networking.
- **Dev hosts** end in `.test` to avoid colliding with real domains (`panda-estampados.test`).
- **TLS in prod**: Caddy auto-manages it via `auto_https on` (change to `on` in prod, leave `off` in dev).

## Trigger patterns

Activate when:

- Editing `Caddyfile` (root).
- Adding a new domain (needs block in prod + dev).
- Changing internal IPs (must match `docker/instances/production/<slug>/docker-compose.yml`).
- Toggling `auto_https on/off` (critical change - affects TLS).
- Debugging 404 / "bad gateway" - verify active IPs with `docker inspect <container>`.

## Activating Caddyfile

```bash
sudo cp Caddyfile /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl restart caddy
```

Plus `/etc/hosts` entries (see block at the end of the Caddyfile).

## Anti-patterns

- ❌ Mixing prod and dev in the same block (always separate).
- ❌ `auto_https on` in dev (breaks TLS with self-signed).
- ❌ Hardcoded IPs outside the 172.20-23.0.0/24 range (see `docker-stack-orchestration`).
- ❌ Hosts without wildcard (`*.`) covering common subdomains (`admin.`, `api.`, etc.).
- ❌ Leaving `admin off` in prod (should be `admin localhost:2019` or disabled by network).

## Adding a new domain

1. Identify the network in `docker/instances/production/<slug>/docker-compose.yml` → network + IPs.
2. Add block in `Caddyfile` (prod + dev).
3. Validate: `sudo caddy validate --config /etc/caddy/Caddyfile`.
4. Restart: `sudo systemctl restart caddy`.
5. Document the host in the comments section of the Caddyfile.

## File map

```
Caddyfile ← root, single source
docker/instances/production/<slug>/ ← IPs per store
docker/instances/development/<slug>/ ← host ports
scripts/docker-stack.sh ← start/stop dev
```

## Related skills

- `docker-stack-orchestration` - internal IPs.
- `laravel-multi-tenant-patterns` - backend `ResolveStoreFromDomain`.
- `astro-themes-and-palette` - frontend branding.
