# Assets — Convencion y estructura

> **Última actualizacion:** 2026-10-19 (refactor per-tenant literal:
> `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/`)
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
> **Politica per-tenant para productos (Oct 2026)**: cada tienda tiene
> SU carpeta de productos en `assets/products/{tenant-slug}/...`. Si una
> tienda no vende productos (ej: estampados), su carpeta existe con un
> `.gitkeep` para ser visible en git pero vacía. Ver seccion
> "**Imagenes de producto (Categoria D)**" abajo.

---

## Estructura

```txt
assets/
├── branding/                          # Categoria A — per-tenant, configurable
│   ├── panda-estampados/
│   │   ├── logo.webp                  # Logo principal
│   │   └── favicon.webp               # Favicon del browser
│   ├── kitsune/                       # Misma estructura
│   ├── mm-boutique/                   # Misma estructura
│   ├── morales-store/                 # Misma estructura
│   └── instrumentos-musicales-la-voz/  # Misma estructura (cuando existan logos)
│
├── products/                          # Categoria D — imagenes de productos per-tenant
│   ├── panda-estampados/              # VACÍO (.gitkeep) — Panda vende estampados, no guitarras
│   │   └── .gitkeep
│   ├── kitsune/                       # VACÍO (.gitkeep) — figs/anime
│   ├── mm-boutique/                   # VACÍO (.gitkeep) — vestidos
│   ├── morales-store/                 # VACÍO (.gitkeep)
│   └── instrumentos-musicales-la-voz/  # Tenant que vende instrumentos musicales
│       ├── gibson/                    # brand-slug
│       │   └── gibson-mod-collection-sg-standard-holy-shift/
│       │       └── holy-shift/        # variant-slug
│       │           └── *.webp         # Multiples sizes (400, 800, 1200, 1600)
│       └── fender/                    # brand-slug
│           └── american-professional-jaguar/
│               └── 3-color-sunburst/
│                   └── *.webp
│
├── frontend/                          # Categoria B — assets estaticos del framework
│   ├── amico/
│   │   └── auth/                      # SVG illustrations para paginas de auth
│   │       ├── login.svg
│   │       └── ...
│   └── icons/                         # Iconos del sistema (si aplica)
│
└── shared/                            # Categoria C — compartida entre tiendas
    └── icons/
        └── social/
            ├── facebook.webp         # Icono generico Facebook
            └── instagram.webp        # Icono generico Instagram
```

### Las 4 categorias

| Categoria | Path en repo | Path en FTP | Quien lo sirve |
| --- | --- | --- | --- |
| **A. Per-tenant** | `assets/branding/{slug}/` | `tenants/{slug}/branding/` | Endpoint proxy del backend |
| **D. Products** | `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/` | `tenants/{slug}/products/{slug}/{hash12}.webp` | Endpoint proxy del backend |
| **C. Shared** | `assets/shared/` | `shared/` | Endpoint proxy del backend |
| **B. Frontend** | `assets/frontend/` | NO va al FTP | Web server directo vía `frontend/public/assets/` symlink |

---

## Imagenes de producto (Categoria D)

**Desde Oct 2026**, las imagenes de producto en `assets/products/`
estan organizadas **per-tenant literal**. Cada tienda tiene SU carpeta:

`assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/*.webp`

### Contrato

- **`tenant-slug`**: kebab-case, matchea `Store.slug`
  (`panda-estampados`, `kitsune`, `instrumentos-musicales-la-voz`).
- **`brand-slug`**: kebab-case, nombre canonico del fabricante
  (`gibson`, `fender`, futuro: `epiphone`, `mesa-boogie`, etc).
- **`product-slug`**: kebab-case, handle del producto
  (`gibson-mod-collection-sg-standard-holy-shift`).
- **`variant-slug`**: kebab-case, identificador de la variant concreta
  (`holy-shift`, `3-color-sunburst`).
- Los archivos `.webp` siguen las convenciones de `assets-naming-convention.md`:
  multiples sizes (400, 800, 1200, 1600), extension lowercase.

### Carpetas vacias con `.gitkeep`

Una tienda que **aún no vende productos musicales** (Panda, Kitsune,
MM, Morales) tiene su carpeta `assets/products/{tenant-slug}/.gitkeep`
para que git la trackee. Esto hace explícita la **ausencia** de catálogo
(mejor que "no hay carpeta" — demuestra intención).

### Por que per-tenant literal (vs un `_images/` + `_manifest/`)

**Decisión arquitectónica registrada en Oct 2026** (luego de evaluar
ambas opciones):

| Opción | Modelo | Trade-off |
|---|---|---|
| (A) Per-tenant literal (esta) | Cada tenant dueño de SU dataset | Si 2 tenants comparten 1 producto, hay duplicación de webps |
| (B) Global `_images/` + per-tenant `_manifest/` | Webps viven una vez, manifests declaran quién vende qué | Mayor abstracción, segundo nivel de indirección |

**Por qué A ganó**:
- La duplicación solo ocurre si 2 tenants venden el mismo producto.
  Hoy (Oct 2026) ningún producto se vende en 2 tenants — cada tienda
  tiene su catalogo único.
- La simplicidad del filesystem (1 nivel menos, sin manifests) hace
  el `find` y `grep` triviales.
- El modelo de BD ya tiene `product_stores` para resolver
  publicacion per-tenant en runtime. El filesystem refleja el ownership
  claro.

**Trade-off aceptado**: si en el futuro 2 tenants comparten un mismo
producto, se duplicaran webps. En ese momento el costo de migrar a
opción B es un `git mv` y reescribir `_manifest/` — barato, ~30min.

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
| `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/*.webp` | (gestionado por `assets:backfill-images` — hashea con `StorageKeyDeriver` y sube a `tenants/{tenant-slug}/products/{product-slug}/{hash12}.webp`) |
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

- **Binarios en git de la app** = antipatron (rompe clone, infla .git,
  no se puede ignorar selectivamente).
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

### Categoria D (producto nuevo o tenant que empieza a vender productos)

**Caso 1: tenant nuevo empieza a vender productos musicales**

1. Crear `assets/products/{tenant-slug}/` (vacío con `.gitkeep` si
   no hay imagenes todavia).
2. Para cada producto, crear la estructura
   `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/`.
3. Optimizar webps multi-size con
   `scripts/convert-product-images.sh` o `scripts/fetch-product-images.sh`.
4. Commit + PR.
5. Actualizar `backend/database/seeds/products.json` con el handle,
   variants, specs y precios.
6. `php artisan db:seed --class=ProductSeeder` para poblar la BD.

**Caso 2: tenant existente agrega un producto nuevo a SU catalogo**

1. Crear la carpeta
   `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/`.
2. Optimizar webps multi-size.
3. Commit + PR.
4. Actualizar seeder JSON + ejecutar `db:seed`.

**Caso 3: tenant decide compartir un producto con otro tenant**

1. Decidir si se duplica webps (Opción A) o se migra a estructura con
   `_images/` + `_manifest/` (Opción B).
2. Si se duplica: copiar la carpeta del producto a la del segundo tenant.
3. Si se migra: ver `docs/architecture/assets-naming-convention.md` §11
   para la mecánica.

---

## Naming estricto

Ver `docs/architecture/assets-naming-convention.md` seccion 2.2.

Resumen:

- Slugs de tenant en kebab-case: `panda-estampados`, `kitsune`,
  `instrumentos-musicales-la-voz`, etc.
- Brand-slug en kebab-case: `gibson`, `fender`, futuro: `epiphone`,
  `mesa-boogie`, etc.
- Asset-types canonicos de branding: `logo`, `favicon`, `og-image`,
  `cover`, `login-logo`.
- Hash deterministico para uploads de admin (NO aplica a brand assets
  que son fijos).
- Extensiones lowercase: `.webp`, `.svg`, `.png`, `.jpg`.
