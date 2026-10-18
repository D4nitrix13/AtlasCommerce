# Assets — Convencion y estructura

> **Última actualizacion:** 2026-10-18 (refactor desde `brand-assets/` +
> `frontend/public/images/`)
>
> Los assets visuales del proyecto (logos, favicons, ilustraciones del
> sistema, imágenes de producto, social icons) viven en ESTE directorio
> del repo, NO en `backend/public/` ni en `frontend/public/`.
>
> Esto permite:
>
> - **Versionarlos en git** (single source of truth, revisable en PRs).
> - **Reutilizarlos** entre dev/prod sin duplicar storage.
> - **Servirlos via FTP** con la convencion `tenants/{slug}/branding/`
>   y `shared/` (ver `docs/architecture/assets-naming-convention.md`).
>
> El sync `assets:sync-from-public` lee ESTE directorio y sube al FTP.

---

## Estructura

```txt
assets/
├── branding/                        # Categoria A — per-tenant, configurable
│   ├── panda-estampados/
│   │   ├── logo.webp                # Logo principal
│   │   └── favicon.webp             # Favicon del browser
│   ├── kitsune/                     # Misma estructura
│   ├── mm-boutique/                 # Misma estructura
│   ├── morales-store/               # Misma estructura
│   └── instrumentos-musicales-la-voz/  # Pendiente: crear archivos
│
├── products/                        # Categoria D — imagenes de productos
│   ├── fender-american-professional-stratocaster/
│   │   └── 3-color-sunburst/
│   │       └── *.webp               # Multiples sizes (400, 800, 1200, 1600)
│   ├── gibson-mod-collection-es-335-smooth-sepia/
│   │   └── smooth-sepia/
│   │       └── *.webp
│   └── ...
│
├── frontend/                        # Categoria B — assets estaticos del framework
│   ├── amico/
│   │   └── auth/                    # SVG illustrations para paginas de auth
│   │       ├── login.svg
│   │       └── ...
│   └── icons/                       # Iconos del sistema (si aplica)
│
└── shared/                          # Categoria C — compartida entre tiendas
    └── icons/
        └── social/
            ├── facebook.webp         # Icono generico Facebook
            └── instagram.webp        # Icono generico Instagram
```

### Las 4 categorias

| Categoria | Path en repo | Path en FTP | Quien lo sirve |
| --- | --- | --- | --- |
| **A. Per-tenant** | `assets/branding/{slug}/` | `tenants/{slug}/branding/` | Endpoint proxy del backend |
| **D. Products** | `assets/products/{brand}/{product}/{variant}/` | `tenants/{slug}/products/{product}/{hash}.webp` | Endpoint proxy del backend |
| **C. Shared** | `assets/shared/` | `shared/` | Endpoint proxy del backend |
| **B. Frontend** | `assets/frontend/` | NO va al FTP | Web server directo vía `frontend/public/assets/` symlink |

---

## Como se sincronizan con el FTP

El command `php artisan assets:sync-from-public` lee ESTE directorio
y sube al FTP siguiendo el mapping:

| Source en repo | Destino en FTP |
| --- | --- |
| `assets/branding/{slug}/logo.webp` | `tenants/{slug}/branding/logo.webp` |
| `assets/branding/{slug}/favicon.webp` | `tenants/{slug}/branding/favicon.webp` |
| `assets/shared/icons/social/facebook.webp` | `shared/icons/social/facebook.webp` |
| `assets/shared/icons/social/instagram.webp` | `shared/icons/social/instagram.webp` |
| `assets/products/{brand}/{product}/{variant}/*.webp` | (gestionado por `assets:backfill-images`) |
| `assets/frontend/amico/auth/*.svg` | NO se sube (categoria B) |

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

- **Binarios en git de la app** = antipatron (rompe clone, infla .git, no
  se puede ignorar selectivamente).
- **Doble storage** (git + FTP) solo en este directorio. Es el unico
  lugar donde es aceptable porque es la source of truth.
- **Frontend sirve categoria B** (amico SVGs) desde su propio
  `frontend/public/images/amico/` despues de copiar (ver seccion
  "Frontend workflow").

---

## Frontend workflow

Los archivos en `assets/frontend/amico/auth/*.svg` (categoria B) se
sirven directamente al frontend vía symlink `frontend/public/assets/
-> ../../assets/`. Asi el frontend los sirve como `/assets/frontend/
amico/auth/login.svg` sin pasar por el endpoint proxy.

Para dev el symlink se crea manualmente (o via script de setup). En
prod, el build del frontend respeta el symlink o lo reemplaza con
copia durante el build de la imagen Docker.

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
