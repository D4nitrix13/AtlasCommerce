# Brand Assets — Convencion y estructura

> **Última actualizacion:** 2026-08-25
>
> Los assets de marca de las tiendas (logos, favicons, social icons,
> ilustraciones) viven en ESTE directorio del repo, NO en
> `backend/public/` ni en `frontend/public/`.
>
> Esto permite:
> - **Versionarlos en git** (single source of truth, revisable en PRs).
> - **Reutilizarlos** entre dev/prod sin duplicar storage.
> - **Servirlos via FTP** con la convencion `tenants/{slug}/branding/`
>   y `shared/` (ver `docs/architecture/assets-naming-convention.md`).
>
> El sync `assets:sync-from-public` lee ESTE directorio y sube al FTP.

---

## Estructura

```txt
brand-assets/
├── store/                           # Categoria A — per-tenant, configurable
│   ├── panda-estampados/
│   │   ├── logo.webp                # Logo principal
│   │   ├── favicon.webp             # Favicon del browser
│   │   ├── og-image.webp             # Open Graph (redes sociales)
│   │   ├── cover.webp                # Hero del home
│   │   └── login-logo.webp           # Logo para /auth/login
│   ├── kitsune/                      # Misma estructura
│   ├── mm-boutique/                 # Misma estructura
│   └── morales-store/               # Misma estructura
│
├── shared/                          # Categoria C — compartida entre tiendas
│   ├── icons/
│   │   └── social/
│   │       ├── facebook.webp         # Icono generico Facebook
│   │       └── instagram.webp        # Icono generico Instagram
│   ├── brand/                       # Futuro: brand por defecto
│   └── placeholders/                # Futuro: OG/cover placeholders
│
└── frontend/                        # Categoria B — assets estaticos del framework
    └── amico/
        └── auth/                    # SVG illustrations para paginas de auth
            ├── forgot-password.svg
            ├── login.svg
            ├── signup.svg
            ├── reset-password.svg
            ├── success.svg
            └── verified.svg
```

### Las 3 categorias

| Categoria | Path en repo | Path en FTP | Quien lo sirve |
| --- | --- | --- | --- |
| **A. Per-tenant** | `brand-assets/store/{slug}/` | `tenants/{slug}/branding/` | Endpoint proxy del backend |
| **C. Shared** | `brand-assets/shared/` | `shared/` | Endpoint proxy del backend |
| **B. Frontend** | `brand-assets/frontend/` | NO va al FTP | Web server directo desde `frontend/public/` |

---

## Como se sincronizan con el FTP

El command `php artisan assets:sync-from-public` lee ESTE directorio
y sube al FTP siguiendo el mapping:

| Source en repo | Destino en FTP |
| --- | --- |
| `brand-assets/store/{slug}/logo.webp` | `tenants/{slug}/branding/logo.webp` |
| `brand-assets/store/{slug}/favicon.webp` | `tenants/{slug}/branding/favicon.webp` |
| `brand-assets/shared/icons/social/facebook.webp` | `shared/icons/social/facebook.webp` |
| `brand-assets/shared/icons/social/instagram.webp` | `shared/icons/social/instagram.webp` |
| `brand-assets/frontend/amico/auth/*.svg` | NO se sube (categoria B) |

Para correr la sincronizacion:

```bash
./scripts/docker-stack.sh exec <stage> <store> backend \
  /usr/local/bin/php artisan assets:sync-from-public
```

Opciones del command:

- `--store={slug}` — solo sincroniza una tienda especifica.
- `--dry-run` — reporta lo que haria sin escribir.
- `--force` — sobrescribe archivos existentes en el FTP.

---

## Por que NO estan en `backend/public/` ni `frontend/public/`

- **Binarios en git de la app** = antipatron (rompe clone, infla .git, no se puede ignorar selectivamente).
- **Doble storage** (git + FTP) solo en este directorio. Es el unico lugar donde es aceptable porque es la source of truth.
- **Frontend sirve categoria B** (amico SVGs) desde su propio `frontend/public/images/amico/` despues de copiar (ver seccion "Frontend workflow").

---

## Frontend workflow

Los archivos en `brand-assets/frontend/amico/auth/*.svg` (categoria B)
se copian a `frontend/public/images/amico/auth/*.svg` durante el
setup del dev stack. Asi el frontend los sirve directo desde el web
server sin pasar por el endpoint proxy.

Para dev:

```bash
# Una sola vez cuando se hace setup
cp brand-assets/frontend/amico/auth/*.svg frontend/public/images/amico/auth/

# O via el script de setup (TBD)
./scripts/docker-stack.sh setup dev  # lo automatiza
```

Para prod: el build del frontend incluye esos archivos via el script
de deploy (TBD).

**Naming**: los archivos en `frontend/amico/auth/` NO llevan sufijo
`-amico` porque ya estan dentro del directorio `amico/`. La convencion
es que el directorio padre provee el namespace, el archivo solo
describe el asset concreto.

---

## Anadir un nuevo asset

### Categoria A (logo de un nuevo tenant)

1. Optimizar el archivo (webp o svg, <100KB si es webp).
2. Crear `brand-assets/store/{slug}/logo.webp` (o favicon, og, etc).
3. Commit + PR (el reviewer valida el contenido del archivo).
4. Correr `assets:sync-from-public` en cada instancia (dev/prod).
5. El backend ya tiene el path configurado en el seed JSON
   (`logo_path: "tenants/{slug}/branding/logo.webp"`).

### Categoria C (icono compartido)

1. Optimizar.
2. Crear `brand-assets/shared/icons/{name}.webp`.
3. Commit + PR.
4. Correr `assets:sync-from-public`.
5. Si hay que actualizar referencias, modificar el theme o el componente
   que lo usa.

### Categoria B (asset de framework)

1. Optimizar.
2. Crear `brand-assets/frontend/amico/auth/{name}.svg`.
3. Commit + PR.
4. Copiar a `frontend/public/images/amico/auth/{name}.svg` (manual o
   via script de setup).
5. Si hay referencias, actualizar el componente que lo usa.

---

## Naming estricto

Ver `docs/architecture/assets-naming-convention.md` seccion 2.2.

Resumen:
- Slugs de tenant en kebab-case: `panda-estampados`, `kitsune`, etc.
- Asset-types canonicos: `logo`, `favicon`, `og-image`, `cover`, `login-logo`.
- Hash deterministico para uploads de admin (NO aplica a brand assets
  que son fijos).
- Extensiones lowercase: `.webp`, `.svg`, `.png`, `.jpg`.