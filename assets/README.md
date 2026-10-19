# Assets — Convencion y estructura

> **Última actualizacion:** 2026-10-19 (refactor per-tenant via
> `_images/` + `_manifest/`)
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
>
> **Politica de imagenes de producto (Oct 2026)**: las imagenes viven en
> `assets/products/_images/{brand}/{product}/{variant}/*.webp` (global,
> single source) y cada tenant declara en
> `assets/products/_manifest/{tenant-slug}.json` que productos vende. Un
> mismo webp puede ser referenciado por multiples tenants sin duplicarse.
> Ver seccion "**Imagenes de producto (Categoria D)**" abajo.

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
├── products/                        # Categoria D — imagenes de productos (per-tenant via manifest)
│   ├── _images/                     # Imagenes compartidas (single source of truth)
│   │   ├── gibson/                  # brand-slug
│   │   │   └── gibson-mod-collection-sg-standard-holy-shift/
│   │   │       └── holy-shift/        # variant-slug
│   │   │           └── *.webp         # Multiples sizes (400, 800, 1200, 1600)
│   │   └── fender/                  # brand-slug
│   │       └── american-professional-jaguar/
│   │           └── 3-color-sunburst/
│   │               └── *.webp
│   └── _manifest/                   # Que productos vende cada tenant (auditable en PRs)
│       ├── panda-estampados.json          →  []
│       ├── kitsune.json                   →  []
│       ├── mm-boutique.json               →  []
│       ├── morales-store.json             →  []
│       └── instrumentos-musicales-la-voz.json  →  [15 productos Fender+Gibson]
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
| **D. Products** | `assets/products/_images/{brand}/{product}/{variant}/` + manifest en `_manifest/{tenant}.json` | `tenants/{slug}/products/{slug}/{hash12}.webp` | Endpoint proxy del backend |
| **C. Shared** | `assets/shared/` | `shared/` | Endpoint proxy del backend |
| **B. Frontend** | `assets/frontend/` | NO va al FTP | Web server directo vía `frontend/public/assets/` symlink |

---

## Imagenes de producto (Categoria D)

**Desde Oct 2026**, las imagenes de producto en `assets/products/`
siguen un split de dos sub-ramas:

### `_images/` — datos compartidos (single source of truth)

`_images/{brand-slug}/{product-slug}/{variant-slug}/*.webp`

- Aqui viven los webps reales, una sola vez.
- Estructura canonica alineada con `docs/architecture/assets-naming-convention.md`
  seccion 2.2 — incluye `brand-slug` como directorio padre (a diferencia
  de versiones previas que tenian las carpetas flat).
- Un mismo webp puede ser **referenciado por N tenants** sin duplicarse.
- Multiples sizes: `400x`, `800x`, `1200x`, `1600x` (alto proporcional
  al aspect ratio del source original).

### `_manifest/` — declaracion per-tenant (auditable en PR)

`_manifest/{tenant-slug}.json`

- Cada tenant declara que productos vende en formato JSON.
- Formato: lista de objetos `{handle, brand_slug, category_slug, image_path}`.
- Productos NO duplicados: un mismo `handle` puede aparecer en N manifests
  (un producto es global, cada tenant decide si lo publica).
- PR review: si un dev agrega `"handle": "fender-stratocaster"` al
  manifest de La Voz, el reviewer entiende inmediatamente que ese
  producto ahora se publica en La Voz. Cero ambiguedad.

Ejemplo (`_manifest/instrumentos-musicales-la-voz.json`):

```json
{
  "tenant_slug": "instrumentos-musicales-la-voz",
  "products": [
    {
      "handle": "american-professional-jaguar",
      "brand_slug": "fender",
      "category_slug": "guitarra-electrica",
      "image_path": "_images/fender/american-professional-jaguar"
    },
    {
      "handle": "gibson-mod-collection-sg-standard-holy-shift",
      "brand_slug": "gibson",
      "category_slug": "guitarra-electrica",
      "image_path": "_images/gibson/gibson-mod-collection-sg-standard-holy-shift"
    }
  ]
}
```

### Por que este split (vs `assets/{tenant}/products/` literal)

- **Sin duplicacion**: si La Voz Y Panda venden el mismo Stratocaster,
  el webp vive una sola vez en `_images/fender/...` y dos manifests lo
  referencian.
- **Compatible con `product_stores`**: el modelo de BD ya tiene la
  pivot `product_stores` que define "que producto publica que tienda".
  El manifest refleja eso en el filesystem.
- **PR-friendly**: cada cambio de "que vende cada tienda" toca SOLO un
  archivo JSON, no mueve webps.
- **Backups faciles**: si un tenant desaparece, su manifest se borra.
  Los webps en `_images/` siguen vivos para los demas tenants.

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
2. Crear `assets/branding/{slug}/logo.webp` (o favicon, og, etc).
3. Commit + PR (el reviewer valida el contenido del archivo).
4. Correr `assets:sync-from-public` en cada instancia (dev/prod).
5. El backend ya tiene el path configurado en el seed JSON
   (`logo_path: "tenants/{slug}/branding/logo.webp"`).

### Categoria C (icono compartido)

1. Optimizar.
2. Crear `assets/shared/icons/{name}.webp`.
3. Commit + PR.
4. Correr `assets:sync-from-public`.
5. Si hay que actualizar referencias, modificar el theme o el componente
   que lo usa.

### Categoria B (asset de framework)

1. Optimizar.
2. Crear `assets/frontend/amico/auth/{name}.svg`.
3. Commit + PR.
4. Copiar a `frontend/public/images/amico/auth/{name}.svg` (manual o
   via script de setup).
5. Si hay referencias, actualizar el componente que lo usa.

### Categoria D (producto nuevo o nuevo tenant vende un producto existente)

**Caso 1: agregar imagen de un producto NUEVO**

1. Optimizar los webps multi-size (400, 800, 1200, 1600) con
   `scripts/convert-product-images.sh` o `scripts/fetch-product-images.sh`.
2. Crear `assets/products/_images/{brand-slug}/{product-slug}/{variant-slug}/*.webp`.
3. Commit + PR (el reviewer valida las imagenes por PR).
4. Actualizar el manifest del tenant appropriate:
   `_manifest/{tenant-slug}.json` agregando el producto al array.
5. Actualizar `backend/database/seeds/products.json` con el handle,
   variants, specs y precios.
6. `php artisan db:seed --class=ProductSeeder` para poblar la BD.
7. `php artisan assets:sync-from-public --store={tenant-slug}` para
   subir al FTP (hashea via `StorageKeyDeriver`).

**Caso 2: tenant existente empieza a vender un producto ya publicado**

1. Abrir `assets/products/_manifest/{tenant-slug}.json`.
2. Agregar el objeto `{handle, brand_slug, category_slug, image_path}`
   al array `products`.
3. Commit + PR (diff minimal, sin tocar imagenes).
4. `php artisan tenants:bootstrap {tenant-config}` — el seeder del
   tenant lee el manifest y crea la fila `product_stores`.

---

## Naming estricto

Ver `docs/architecture/assets-naming-convention.md` seccion 2.2.

Resumen:

- Slugs de tenant en kebab-case: `panda-estampados`, `kitsune`, etc.
- Asset-types canonicos: `logo`, `favicon`, `og-image`, `cover`, `login-logo`.
- Hash deterministico para uploads de admin (NO aplica a brand assets
  que son fijos).
- Extensiones lowercase: `.webp`, `.svg`, `.png`, `.jpg`.
