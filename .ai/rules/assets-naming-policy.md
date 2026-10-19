# Asset Naming & Storage Policy

> Regla durable para agentes AI y humanos que tocan el repo `assets/`.
> Cargada automáticamente al inicio de cada session que toque archivos
> bajo `assets/products/`, `assets/branding/`, o `assets/shared/`.
>
> Source of truth narrativa: `docs/architecture/assets-naming-convention.md`
> + `assets/README.md` (sección "Imagenes de producto (Categoria D)").
> Si esta regla contradice la doc, la doc gana (esta regla es enforcement).

---

## Regla de oro (TL;DR)

```
assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/{role}-{WxH}.webp
         ↓ nombre legible, commiteado al repo, NO hasheado
         ↓ al hashear, el FTP usa:  tenants/{tenant-slug}/products/{product-slug}/{hash12}.webp
```

**El repo tiene nombre legible, el FTP tiene nombre hasheado, los dos son
fuentes separadas.** No mezcles.

---

## 1. Estructura del repo (lo que commiteas)

```
assets/
├── branding/                                  Categoría A — per-tenant, configurable
│   ├── panda-estampados/
│   │   ├── logo.webp
│   │   └── favicon.webp
│   ├── kitsune/
│   ├── mm-boutique/
│   ├── morales-store/
│   └── instrumentos-musicales-la-voz/         (slug kebab-case EXACTO)
│
├── products/                                  Categoría D — per-tenant, per-product
│   ├── panda-estampados/.gitkeep              (vacío si no vende productos)
│   ├── kitsune/.gitkeep
│   ├── mm-boutique/.gitkeep
│   ├── morales-store/.gitkeep
│   └── instrumentos-musicales-la-voz/           (solo La Voz tiene catálogo hoy)
│       ├── gibson/                            (brand-slug)
│       │   └── sg-standard/                   (product-slug)
│       │       └── heritage-cherry/            (variant-slug)
│       │           ├── front-1600x1600.webp    (4 sizes por role)
│       │           ├── front-1200x1200.webp
│       │           ├── front-800x800.webp
│       │           ├── front-400x400.webp
│       │           ├── back-1600x1600.webp
│       │           └── ... (~15 roles posibles)
│       ├── epiphone/   gibson-custom/   kramer/
│
├── frontend/                                  Categoría B — assets del framework
│   ├── amico/auth/   (SVG illustrations)
│   └── favicon/
│
└── shared/                                    Categoría C — cross-tenant
    └── icons/social/
```

### Reglas de naming (en repo)

| Componente | Formato | Ejemplo | Notas |
|---|---|---|---|
| tenant-slug | kebab-case | `instrumentos-musicales-la-voz` | Match exacto `Store.slug` en BD |
| brand-slug | kebab-case | `gibson-custom` (no `gibson_custom`) | Sin underscores, sin CamelCase |
| product-slug | kebab-case | `sg-standard-holy-shift` | match `Product.slug` en BD |
| variant-slug | kebab-case | `heritage-cherry` | match `ProductVariant.finish_slug` en BD |
| role | enum corto | `front`, `back`, `body`, `backbody`, `headstock` | Ver sección 3 para lista completa |
| WxH | width x height | `1600x1600` | sin spaces, lowercase 'x' |

### Anti-patterns (lo que NO debes hacer)

- ❌ `assets/products/la-voz/gibson/SG Standard/Holy Shift/front.WEBP` (espacios, mayúsculas)
- ❌ `assets/products/instrumentos_musicales_la_voz/gibson/...` (underscores en lugar de guiones)
- ❌ `assets/products/Panda Estampados/...` (espacio, mayúscula) — incluso si el tenant tiene mayúsculas en su nombre display
- ❌ `assets/products/instrumentos-musicales-la-voz/gibson/sg-standard/heritage-cherry/front.jpg` (NO usamos JPG/PNG en repo — TODO va WebP)
- ❌ Crear `assets/products/{tenant}/` SIN `.gitkeep` cuando la tienda no vende productos (git no commitea carpetas vacías)
- ❌ Borrar el `.gitkeep` cuando agregas productos a la carpeta (es un placeholder, no un archivo de verdad)

---

## 2. Naming en el FTP (lo que ve el backend al servir)

```
tenants/{tenant-slug}/products/{product-slug}/{hash12}.webp
                ↑                ↑                ↑
                tenant           producto         SHA-256 prefix 12 chars
```

**El FTP path NO contiene el brand ni el variant.** El hash es del CONTENIDO
binario del webp, no del filename. Eso garantiza que:

1. **Anti path-traversal**: el admin no puede inyectar `../../etc/passwd`
2. **Anti enumeración**: nadie puede adivinar el próximo archivo
3. **Anti hotlinking**: el hash no revela qué producto es

### Hashing via `App\Services\Assets\StorageKeyDeriver`

El hash es `substr(sha256($binary), 0, 12)`. **SIEMPRE 12 chars hex**. NO
regeneres el hash manualmente — usa el service.

```php
// CORRECTO
$storageKey = $this->deriver->derive(
    tenantSlug: 'instrumentos-musicales-la-voz',
    collection: 'products',
    entity: $product->slug,
    content: file_get_contents($webpPath),
    extension: 'webp',
);
// → "tenants/instrumentos-musicales-la-voz/products/sg-standard-holy-shift/d41d8cd98f00b204.webp"

// INCORRECTO — NO generes hashes a mano
$storageKey = "products/{$id}.webp";  // ❌
// INCORRECTO — NO uses md5/sha1 NO reversibles
$storageKey = md5($filename);          // ❌
```

---

## 3. Roles de imagen (galleries del PDP)

### Orden semántico (de mayor a menor importancia visual)

```php
// CatalogAssetsSeeder::ROLE_ORDER
'front'           => 1,  // PRIMARY: imagen default en card de producto
'back'            => 2,  // HOVER: aparece al pasar cursor encima
'body'            => 3,  // Vista del cuerpo (alternativa)
'backbody'        => 4,  // Vista trasera del cuerpo
'headstock'       => 5,  // Pala/clavijero (zoom)
'back-headstock'  => 6,  // Reverse headstock
'pickup'          => 7,  // Humbucker/cilindro (zoom)
'knobs'           => 8,  // Perillas de control (zoom)
'side'            => 9,  // Perfil lateral
'case'            => 10, // Estuche (cuando exista)
'beauty'          => 11, // Toma estética adicional
'finish-top'      => 12, // Detalle de acabado
'finish-back'     => 13,
'finish-sides'    => 14,
'binding'         => 15,
'fingerboard'     => 16,
'sn-label'        => 17,
'tailpiece'       => 18,
'finish-peghead'  => 19,
```

### Schema de BD (cómo se guarda en `product_images`)

| Columna | Tipo | Semántica |
|---|---|---|
| `image_url` | VARCHAR(255) | Storage key hasheado (`tenants/.../products/.../{hash}.webp`) |
| `is_primary` | BOOL | `true` SOLO en 1 imagen por variant (la de sort_order=1) |
| `sort_order` | INT | Posición en galería (1=primary, 2=hover, 3+=resto) |
| `alt_text` | VARCHAR(160) | "{Product name} - vista {role}" (e.g. "SG Standard - vista front") |

### Regla de oro de primary

> **Cada variant tiene EXACTAMENTE 1 imagen `is_primary=true`.**
> La primary es `sort_order=1` con el `size=1600` (max resolution).
> Si un variant no tiene una imagen 1600, NO tiene primary (issue conocido).

El seeder lo calcula con `pickPrimaryRole()` que toma el role con
sort_order mínimo disponible + filtra por size=1600. Si no hay
size=1600, la primary queda null y el verify reporta el variant.

---

## 4. Storage y conversion

| Concepto | Repo (`assets/`) | FTP / Storage |
|---|---|---|
| Formato | `.webp` (calidad 85) | `.webp` (mismo) |
| Nombre | legible (`front-1600x1600.webp`) | hasheado (`d41d8cd98f00b204.webp`) |
| Origen | `git push` a GitHub/GitLab | artisan `assets:sync-from-public` o `assets:seed-catalog` |
| Compression | ya aplicada (quality 85, cwebp) | NINGUNA (el archivo YA está WebP) |
| Sizes | 4 (400, 800, 1200, 1600) | 1 (el 1600 — el resto se sirve via frontend srcset o re-convierte) |

### ⚠️ NO reconviertas a WebP

Si ves un `.png` o `.jpg` en `assets/`, eso es un BUG. El script
`scripts/migrate-gibson-images.sh` ya convirtió todo a WebP. Si un
archivo aparece como PNG, alguien lo copió mal o lo commiteó por error.

Si NECESITAS regenerar WebPs desde PNG source:
1. Los PNG originales NO están en el repo (estaban en staging LFS, ahora
   solo en `~/.local/atlascommerce-archive/gibson-images/`)
2. Corre `./scripts/migrate-gibson-images.sh <cat> <brand>` (toma del
   staging y produce WebPs al repo)
3. Commit el resultado
4. Después corre `php artisan assets:seed-catalog` para actualizar BD

---

## 5. Comandos disponibles

| Comando | Para qué | Cuándo correrlo |
|---|---|---|
| `php artisan assets:sync-from-public` | Sube branding/shared al FTP | Después de cambiar logos de tenant |
| `php artisan assets:seed-catalog` | Puebla BD + hashea + sube | Después de migrar/cambiar imágenes |
| `php artisan tenants:bootstrap` | Crea Store/dominios en BD | Una vez por tenant nuevo |
| `./scripts/migrate-gibson-images.sh <cat> <brand>` | Genera WebPs desde PNG | Después de descargar dataset nuevo de gibson.com |
| `php artisan assets:backfill-images` | (legacy, no usar — preferir seed-catalog) | — |

---

## 6. Validación al tocar assets/

Antes de hacer commit que toque `assets/`, verificá:

```bash
# 1. ¿Los nuevos archivos tienen extension .webp?
find assets/products/ -name "*.png" -o -name "*.jpg" | wc -l
# esperado: 0

# 2. ¿Los nombres respetan kebab-case?
find assets/products/ -type d | grep -E '[A-Z]|_' | head
# esperado: vacío (si no, renombrar)

# 3. ¿Hay .gitkeep en tiendas vacías?
ls -la assets/products/{panda-estampados,kitsune,mm-boutique,morales-store}/.gitkeep
# esperado: los 4 existen

# 4. ¿El seeder corre limpio?
php artisan assets:seed-catalog --dry-run
# esperado: 0 errors
```

Si cualquier check falla, **NO commitees** — fix el issue primero.

---

## 7. Cuando el user pida cambios estructurales

Si el user pide "mover todo a per-tenant" o "cambiar el naming":

1. **No rompas el contrato del frontend**: el frontend lee de
   `data/catalog-meta/{cat}/{brand}/.../{finish}/product.json` y de
   `assets/products/{tenant}/{brand}/.../*.webp`. Cambiar la estructura
   rompe TODOS los seeders y TODOS los PDPs renderizados.
2. **No mezcles sistemas de naming**: si el repo tiene `front-1600x1600.webp`
   y el FTP tiene `d41d8cd98f00b204.webp`, NO intentes que el FTP use nombres
   legibles. El hash es DEL CONTENIDO, no del nombre legible. Si quieres
   "nombres legibles en el FTP", re-computa el storage_key y re-corre el seed.
3. **Usa una migration nueva para cambios de schema**: cualquier cambio
   de columna en `products`, `product_variants`, `product_images`,
   `product_stores` requiere una migration. Ver
   `docs/architecture/migration-pattern.md`.

---

## Refs

- `assets/README.md` — convención operativa (doc humana, primera lectura)
- `docs/architecture/assets-naming-convention.md` — spec completa (referencia)
- `docs/architecture/migration-pattern.md` — pattern de migrations
- `backend/app/Services/Assets/StorageKeyDeriver.php` — service de hashing
- `backend/app/Services/Assets/CatalogAssetsSeeder.php` — seeder principal
- `backend/app/Console/Commands/SeedCatalogAssetsCommand.php` — artisan command
- `scripts/migrate-gibson-images.sh` — conversion PNG → WebP
- `docs/architecture/multi-store.md` — modelo multi-tenant BD

Si esta regla contradice cualquiera de los archivos de arriba, gana el
archivo de arriba y esta regla debe actualizarse.
