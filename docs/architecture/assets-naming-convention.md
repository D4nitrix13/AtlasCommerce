# Convención de nombres y almacenamiento de assets

> **Última actualización:** 2026-08-24
>
> Define cómo se nombran, dónde se almacenan y cómo se sirven los
> recursos visuales (imágenes, logos, íconos, favicons) del sistema.
>
> Aplica a **Panda Estampados / Kitsune** y a las instancias single-tenant
> (`mm-boutique`, `morales-store`).

---

## 1. Principio rector — tres categorías, tres lugares

No todos los assets son iguales. Distinguimos tres categorías según
**quién los define** y **con qué frecuencia cambian**:

| Categoría | Quién los define | Frecuencia de cambio | Dónde van | Sirve |
| --- | --- | --- | --- | --- |
| **A. Assets configurables por tenant** | Admin de la tienda | Cada upload o cambio de branding | **FTP / storage centralizado** | Backend Laravel (path en BD) → proxy al frontend |
| **B. Assets estáticos del framework** | Desarrollador | Casi nunca (build time) | **`backend/public/` y `frontend/public/`** | Directo por el web server |
| **C. Assets compartidos de marca** | Desarrollador | Rara vez (release) | **`backend/public/images/shared/`** | Directo por el web server |

### Por qué NO mezclar las tres categorías

- **Performance**: si un asset vive en FTP/storage, cada request al
  frontend hace un round-trip extra al backend para servirlo. Los
  íconos UI y el CSS compilado deben servirse directo desde el web
  server.
- **Disponibilidad**: si el FTP está caído, **solo** se rompen los
  assets de categoría A (logos, fotos de productos). El sitio sigue
  navegable porque el layout, los íconos y el CSS siguen vivos en
  `public/`.
- **Versionado**: los assets de B y C son **código**, van al repo git,
  se revisan en PR, se despliegan con la app. Los de A son **datos**,
  viven en storage, se respaldan aparte.

---

## 2. Categoría A — Assets configurables por tenant

Son los assets que **cambian por tienda y/o por upload del admin**:

- Logos de marca (light, dark, mobile)
- Favicons
- Cover images (hero del home)
- Open Graph images (preview en redes sociales)
- Login logo (imagen en `/auth/login`)
- Imágenes de productos
- Banners de marketing (cupones, promociones)

### 2.1. Convención de paths

Todas las rutas usan **kebab-case para slugs** y **snake_case solo para
separación de palabras dentro del nombre del archivo**:

```text
/srv/ftp/images/tenants/{tenant-slug}/products/{product-slug}/{imagen-hash}.{ext}
/srv/ftp/images/tenants/{tenant-slug}/branding/{asset-type}.{ext}
/srv/ftp/images/tenants/{tenant-slug}/marketing/{campaign-slug}.{ext}
```

Ejemplos concretos:

```text
/srv/ftp/images/tenants/panda-estampados/products/camiseta-oversized/abc123def456.webp
/srv/ftp/images/tenants/panda-estampados/branding/logo.webp
/srv/ftp/images/tenants/panda-estampados/branding/login-logo.webp
/srv/ftp/images/tenants/panda-estampados/branding/favicon.webp
/srv/ftp/images/tenants/panda-estampados/branding/cover.webp
/srv/ftp/images/tenants/panda-estampados/branding/og-image.webp
/srv/ftp/images/tenants/kitsune/products/figura-anime-tokyo/789ghi012jkl.webp
/srv/ftp/images/tenants/kitsune/branding/logo.webp
/srv/ftp/images/tenants/mm-boutique/products/vestido-floreal/mno345pqr678.webp
```

### 2.2. Reglas de naming

| Componente | Regla | Ejemplo válido | Ejemplo inválido |
| --- | --- | --- | --- |
| `{tenant-slug}` | kebab-case, lowercase, matchea `Store.slug` | `panda-estampados`, `kitsune`, `mm-boutique`, `morales-store` | `Panda_Estampados`, `pandaEstampados` |
| `{product-slug}` | kebab-case, lowercase, matchea `Product.slug` | `camiseta-oversized`, `figura-anime-tokyo` | `camiseta_Oversized` |
| `{asset-type}` | snake_case si compuesto, valores canónicos | `logo`, `login-logo`, `favicon`, `cover`, `og-image` | `Logo`, `loginLogo`, `login_logo` |
| `{imagen-hash}` | hash determinístico del archivo subido (ej. `substr(sha256(file), 0, 12)`) | `abc123def456`, `789ghi012jkl` | `Screenshot 2024-01-15.png`, `IMG_1234.jpg` |
| `{ext}` | lowercase, canonicalizado | `webp`, `jpg`, `png`, `svg` | `WEBP`, `jpeg`, `JPG` |

### 2.3. Por qué hash y no nombre original

- **Colisiones**: dos archivos distintos pueden tener el mismo nombre
  original (`logo.png` dos veces al subir). El hash los diferencia.
- **Path traversal**: si el nombre original se usara tal cual, un admin
  malicioso podría subir `../../etc/passwd` y terminar en un path fuera
  del FTP. El hash es seguro.
- **Cache busting**: el hash es estable por contenido. Si cambia el
  contenido, cambia el hash, el browser invalida el cache automáticamente.

### 2.4. Dónde se almacenan físicamente

**Disco configurado en `backend/config/filesystems.php`** como
`ftp`:

```php
'ftp' => [
    'driver' => 'ftp',
    'host' => env('FTP_HOST'),
    'username' => env('FTP_USERNAME'),
    'password' => env('FTP_PASSWORD'),
    // ...
    'root' => env('FTP_ROOT', '/srv/ftp/images'),
    // ...
],
```

El **root por default es `/srv/ftp/images`**, no `/srv/ftp`. Esto
encierra todos los assets en un subdirectorio dedicado:

- Permite crear el directorio con `Storage::makeDirectory()` antes del
  primer upload sin tocar `/srv/ftp` raíz.
- Permite montar el volumen `ftp-data` con permisos restrictivos solo
  sobre `images/` en una migración futura a S3 o similar.
- Evita confusión cuando el FTP se usa para otras cosas en el futuro
  (logs, dumps, etc).

### 2.5. Cómo se sirven al frontend

**El frontend NUNCA accede al FTP directamente.** El flujo es:

1. El admin sube el asset vía API → backend lo guarda en FTP vía
   `Storage::disk('ftp')`.
2. El backend guarda **solo el path relativo** en la BD
   (ej. `tenants/panda-estampados/branding/logo.webp`).
3. Cuando el frontend pide el tema del tenant
   (`GET /api/storefront/theme`), el backend devuelve el path.
4. El frontend resuelve la URL final pegando el path a una **URL base
   servida por el backend** (ej. `GET /api/storefront/assets/{path}`).
5. Ese endpoint hace `Storage::disk('ftp')->get($path)` y devuelve el
   archivo con los headers correctos (`Content-Type`, `Cache-Control`,
   `ETag` basado en mtime+size).

**Por qué proxy y no URL directa al FTP**:

- El FTP no es HTTP, los browsers no lo entienden.
- El FTP puede no ser accesible desde internet (solo Docker network).
- Permite control de acceso (signed URLs, rate limiting).

---

## 3. Categoría B — Assets estáticos del framework

Son los assets que **definen cómo se ve la app** y que **cambian con
cada release**:

- CSS compilado por Vite (`backend/public/build/`)
- JS compilado (`backend/public/build/`)
- Manifest de Vite (`backend/public/build/manifest.json`)
- `favicon.ico` por defecto de Laravel (`backend/public/favicon.ico`)
- `robots.txt` (`backend/public/robots.txt`)
- Iconos UI del frontend (`frontend/public/favicon.svg`)
- `apple-touch-icon.png` (`frontend/public/`)

### 3.1. Convención

Se sirven **directo desde `public/`** sin pasar por Laravel. El web
server (Caddy en prod, Astro dev server en dev) los entrega con la
configuración default.

- Nombres en `kebab-case` o `lowercase`, sin espacios.
- Versión bumped en el nombre cuando cambia el contenido (ej.
  `logo-v2.webp`) solo si el cache del browser es un problema; si no,
  dejar que `Cache-Control` + ETag resuelvan.
- Subdirectorios permitidos: `public/images/`, `public/fonts/`, etc.
  **No** anidar más de 2 niveles.

### 3.2. No commitear en `public/` lo que va en categoría A

Regla fácil: **si el admin de la tienda lo va a querer cambiar**, va a
categoría A (FTP). Si solo lo cambia un dev en un release, va a `public/`.

---

## 4. Categoría C — Assets compartidos de marca

Son assets que **representan la marca de la instancia completa** (no de
un tenant específico) y son leídos tanto por el backend como por el
frontend:

- Logo genérico "Panda + Kitsune" (cuando no hay tenant resuelto)
- Favicon default de la instancia
- OG image default de la instancia
- Cover image default de la instancia
- Iconos de redes sociales (Facebook, Instagram)

### 4.1. Convención de paths

Viven en `backend/public/images/shared/` y se configuran desde
`backend/config/brand.php`:

```php
'default_logo_path'      => 'images/shared/brand/app-logo.webp',
'default_favicon_path'    => 'images/shared/brand/favicon.webp',
'default_og_image_path'   => 'images/shared/placeholders/store-og-placeholder.webp',
'default_cover_image_path'=> 'images/shared/placeholders/store-cover-placeholder.webp',
```

Los `path` aquí son **relativos a `public/`** (Laravel los resuelve
con `asset()` o `mix()`).

### 4.2. Sirven como fallback

Estos paths se usan **solo cuando el tenant no tiene su propio asset
configurado** (categoría A vacía). El helper que arma el theme del
tenant usa:

```php
$logo = $storeTheme->logo_path
    ?? config('brand.default_logo_path');
```

---

## 5. Resumen rápido

| ¿Qué asset? | ¿Quién lo cambia? | ¿Dónde vive? | ¿Cómo se sirve? |
| --- | --- | --- | --- |
| Logo de Panda Estampados | Admin | FTP `tenants/panda-estampados/branding/logo.webp` | Backend proxy `/api/storefront/assets/...` |
| Logo genérico fallback | Dev (release) | `backend/public/images/shared/brand/app-logo.webp` | Directo desde `public/` |
| CSS compilado | Dev (build) | `backend/public/build/assets/abc123.css` | Directo desde `public/` |
| Foto de producto de Kitsune | Admin | FTP `tenants/kitsune/products/{slug}/{hash}.webp` | Backend proxy |
| Favicon default de Laravel | Dev (raro) | `backend/public/favicon.ico` | Directo |
| Icono UI (ej. shopping cart) | Dev (release) | `frontend/public/icons/cart.svg` | Astro/Astro assets pipeline |

---

## 6. Anti-patrones explícitos

Estas cosas **NO se hacen** en este proyecto:

1. ❌ **Crear `/home/d4nitrix13/Code/AtlasCommerce/ftp-storage/`** con
   imágenes de productos en el repo git. Los assets de tenant son
   datos, no código.
2. ❌ **Servir el CSS compilado desde FTP**. El CSS va a `public/build/`,
   siempre.
3. ❌ **Hardcodear URLs absolutas** (`http://panda-estampados.com/images/...`)
   en el código. Usar `route()` o helpers de URL, siempre.
4. ❌ **Usar el nombre original del archivo subido** como nombre en el
   storage. Siempre hashear.
5. ❌ **Subir un asset directamente al FTP sin pasar por la API**. La API
   valida extensión, hashea, crea el registro en BD, escribe en FTP.
   El FTP no acepta uploads anónimos.
6. ❌ **Mezclar categorías**. Si está en categoría A, va a FTP. Si está
   en B o C, va a `public/`. No hay excepción.

---

## 7. Referencias

- `backend/config/filesystems.php` — definición de los 4 disks
  (`local`, `public`, `s3`, `ftp`).
- `backend/config/brand.php` — paths default de categoría C.
- `backend/app/Models/Stores/StoreTheme.php` — modelo que guarda los
  paths de categoría A.
- `backend/database/migrations/stores/2026_08_02_000002_create_store_themes_table.php` —
  schema de la tabla que guarda los paths de categoría A.
- `backend/database/migrations/2026_06_28_193338_create_media_table.php` —
  tabla `media` (Spatie MediaLibrary) para uploads asociados a modelos.
- `docs/architecture/multi-store.md` — qué assets se comparten vs se
  separan entre Panda y Kitsune.