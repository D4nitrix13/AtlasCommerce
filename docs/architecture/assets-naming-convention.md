# Convención de nombres y almacenamiento de assets

> **Última actualización:** 2026-10-17
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

## 6. Phased rollout

El subsystem de assets no entra completo el día uno: se introduce por
fases para minimizar el riesgo de romper la PDP con paths legacy. Esta
sección documenta qué fase cubre qué y cuál es el source-of-truth mientras
convivan el código nuevo y el viejo.

### 6.1. Tabla de fases

| Fase | Scope | Estado |
| --- | --- | --- |
| 2 (DONE) | `StorageKeyDeriver` + soft-mode validation en `AssetController` | mergeada (`10d55e64`) |
| 3 (DONE) | HMAC signed URLs + cache config-driven + 404 enumeration logging | mergeada (`63088b23`) |
| 4 (DONE) | Pattern de migrations (universal + extension table) | mergeada (`ff0309f0`) |
| 5 | Backfill de imágenes existentes al nuevo formato | comando + docs mergeados; ejecución operativa pendiente |
| 6 | Flip soft → strict + cleanup legacy `fenderImageUrl()` + flip `url_signing.required=true` | pendiente |

### 6.2. Drift conocido entre doc y código

Estos puntos divergen del doc y se cierran en la fase indicada. Mientras
tanto, el **código es la fuente de verdad**.

- **`FTP_ROOT`**: este doc dice `/srv/ftp/images` (§2.4, línea 109), pero
  `backend/config/filesystems.php:76` default es `/` (raíz del HOME del
  FTP user). La razón del cambio es que el command `assets:sync-from-public`
  crea automáticamente los subdirectorios `tenants/{slug}/...` y `shared/...`
  bajo el root que defina el host, y `/srv/ftp/images` solo era válido en
  la deploy inicial. Corregido en Fase 3.
- **`ETag`**: este doc dice "basado en mtime+size" (§2.5, línea 138), pero
  `backend/app/Http/Controllers/Api/Storefront/AssetController.php`
  usa `md5($contents)`. La razón del cambio es que `md5(content)` es
  invariante a rotación de archivos en el FTP (un `mv` mantiene el mtime
  accidentalmente), mientras que el contenido garantiza invalidación real.
  Corregido en Fase 3.
- **`cache TTL`**: el código de Fase 2 tenía `Cache::remember(..., 86400)`
  hard-coded en `AssetController`; el default aprobado en este doc es 300s
  (5min). La razón del cambio es que 86400s mantenía assets stale por 24h
  después de cualquier upload; 300s es suficiente given el `Cache-Control:
  max-age=31536000` que el browser ya respeta. Corregido en Fase 3
  vía `config('assets.cache_ttl')`.

### 6.3. Runtime protections

Fase 3 cierra tres gaps de seguridad/operaciones del `AssetController`
identificados en el audit. Cada uno es **gated por config** para
permitir rollout gradual (default conservativo, flip explícito cuando
la instancia esté lista).

#### 6.3.1. URL signing

- Servicio: `App\Services\Assets\UrlSigner` (puro, determinístico,
  timing-safe).
- Algoritmo: HMAC-SHA256 sobre payload canónico `path|expires`.
- Comparación: `hash_equals` (evita timing attacks sobre la signature).
- Flag: `config('assets.url_signing.required')` (default `false`).
- Env override: `ASSETS_URL_SIGNING_REQUIRED` en `.env`.
- TTL por defecto: 300s (`config('assets.url_signing.ttl')`, env
  `ASSETS_URL_SIGNING_TTL`).
- Secret: `config('assets.url_signing.secret')` con fallback a
  `config('app.key')` (env `ASSETS_URL_SIGNING_SECRET`).
- Query format: `?expires=<unix_ts>&sig=<hex64>`.
- Enforcement en `AssetController`: cuando `required=true`, la request
  se aborta con 403 (`'Missing signature.'`) si los params faltan, o
  403 (`'Invalid or expired signature.'`) si la firma no matchea o
  expiró. Cuando `required=false` (default Fase 3), el chequeo no se
  ejecuta — comportamiento idéntico a Fase 2.
- Rollout plan: Fase 6 flipea `required=true` después de que el rate
  de `asset.path.malformed` caiga a ~0 (post-backfill de Fase 5). El
  frontend debe empezar a firmar URLs en el momento del flip.

#### 6.3.2. Cache strategy

- Store: configurable vía `config('assets.cache_store')` (default
  `redis`, env `ASSETS_CACHE_STORE`). El cambio estaba hard-coded a
  `'redis'` en Fase 2 — Fase 3 lo expone para que instancias
  single-tenant o entornos degradados puedan usar `file` o `array`.
- TTL: configurable vía `config('assets.cache_ttl')` (default 300s,
  env `ASSETS_CACHE_TTL`). Drift histórico: Fase 2 usaba 86400s
  hard-coded — ver §6.2 para el rationale del cambio.
- Clave: `ftp-asset:{sha256($path)}` (mismo formato que Fase 2,
  ahora expuesto como constante `AssetController::CACHE_KEY_PREFIX`).
- Invalidation: el command `assets:invalidate-cache` (TBD) borrará
  todas las claves con ese prefijo via Redis `SCAN` (no `KEYS`).

#### 6.3.3. Enumeration detection

- Trigger: cualquier 404 del `AssetController` (path que pertenece
  al tenant pero no existe en FTP).
- Log: `logger()->warning('asset.not_found', [...])` con contexto
  estructurado:
  - `hash`: SHA-256 del path normalizado (no logueamos el path raw
    para no exponer nombres de assets internos).
  - `tenant_slug`: el slug activo que recibió el request.
  - `client_ip`: IP del requester (CDN-aware vía TrustProxies).
  - `user_agent`: truncado a 200 chars (evitar logs gigante si el
    UA viene con beacon data).
- Voluntary logging paralelo: `asset.path.malformed` (Fase 2)
  captura paths que no matchean la convención pero el archivo sí
  existe en FTP. Misma estructura de contexto, mensaje distinto.
- Detección: un spike de `asset.not_found` con muchos hashes
  distintos desde la misma IP/subred indica enumeration. El comando
  `monitor:enumeration-alerts` (TBD) agregará estos eventos contra
  thresholds por tenant.
- Privacidad: el path raw nunca se loguea. Solo el hash SHA-256, que
  es irreversible pero único por path.

---

## 7. Anti-patrones explícitos

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

## 8. Backfill procedure (Fase 5)

Las filas existentes en `product_images.image_url` quedaron con formatos
heterogéneos antes de que esta convención se introdujera: URLs externas
de CDN (`https://cdn.fender.com/...`), paths legacy del disk `local`
(`/images/products/...`), y unas pocas keys ya migradas
(`tenants/{slug}/products/{slug}/{hash}.{ext}`). El comando
`assets:backfill-images` las re-hashea, las re-sube a nuestro FTP, y
reemplaza `image_url` con la storage key canónica — dejando la DB
consistente con el contrato de §2.

El procedimiento siguiente está pensado para ejecutarse **una vez por
instancia**, supervisado, con ventana de mantenimiento corta. No es un
paso de deploy automatizado.

### 8.1. Pre-flight checklist

Antes de correr el comando:

1. **Fases 2-4 mergeadas en master.** El `StorageKeyDeriver`, el
   `AssetController` (con soft mode) y el patrón de migrations tienen
   que estar en producción. Verificar con `git log --oneline -10
   backend/app/Services/Assets/StorageKeyDeriver.php` que la rama de
   release los incluye.
2. **`config/assets.php` con flags esperados.**
   `path_validation.strict` debe seguir en `false` durante el backfill
   — se flipea recién después (ver §8.5). `url_signing.required` debe
   seguir en `false`.
3. **Test manual de `StorageKeyDeriver` + `Storage::disk('ftp')`.**

   ```bash
   php artisan tinker --execute='dump(app(App\Services\Assets\StorageKeyDeriver::class)->derive(tenantSlug: "panda-estampados", collection: "products", entity: "test", content: "smoke-test", extension: "webp"));'
   php artisan tinker --execute='dump(Storage::disk("ftp")->exists("tenants/panda-estampados/products/test/abc123def456.webp"));'
   ```

   La primera llamada devuelve un path `tenants/panda-estampados/products/test/{hash}.webp`;
   la segunda devuelve `false` (no existe todavía, pero el disk responde).
4. **Backup de la DB** (snapshot del tenant a migrar). El comando hace
   rollback por-chunk si la transacción falla, pero un snapshot completo
   permite restore atómico si algo sale mal.
5. **Cache lock liberado.** El comando adquiere
   `Cache::lock('backfill-images', ttl: 600)`. Si una corrida previa
   quedó colgada (TTL 10min), esperar o liberar manualmente:

   ```bash
   redis-cli -n 1 DEL laravel_cache_backfill-images
   ```

### 8.2. Dry-run

Empezar siempre por `--dry-run`. El comando hace HTTP GET de cada URL,
computa el SHA-256, deriva la storage key nueva y reporta stats — pero
**no toca ni FTP ni DB**. Útil para validar que el dataset tiene la
forma esperada.

```bash
cd backend
php artisan assets:backfill-images --dry-run --tenant=panda-estampados
```

Output esperado:

```text
Backfilling product images... (24 encontrados)
  24/24 [============================] 100%
[backfill] processed=24 uploaded=0 skipped=0 failed=0
```

Stats interpretadas:

| Stat | Significado |
| --- | --- |
| `processed` | Filas iteradas (total de `product_images` que matchean el filtro). |
| `uploaded` | **Siempre 0 en dry-run** (no escribe). |
| `skipped` | Filas que ya tienen storage key canónica (no necesitan backfill). |
| `failed` | Filas que el comando no pudo resolver (URL rota, path local inexistente, slug faltante, etc). |

Si `failed > 0` en dry-run, **investigar antes de ejecutar real**. Las
causas más comunes son CDNs externos caídos, archivos locales borrados,
o productos sin `ProductStore` (huérfanos).

### 8.3. Ejecución real

Una vez validado el dry-run:

```bash
php artisan assets:backfill-images --tenant=panda-estampados --chunk=50
```

Sin `--dry-run`, el comando pide confirmación interactiva salvo que se
pase `--force`. Para scripts automatizados (cron, CI) usar siempre
`--force`.

El comando:

1. Adquiere `Cache::lock('backfill-images')` para prevenir concurrencia.
2. Itera `product_images` con `lazyById($chunkSize)` (default 100 filas
   por chunk — bajar a 50 para CDNs lentos).
3. Para cada fila:
   - **Skip** si `image_url` ya matchea la convención.
   - **Skip** si la storage key derivada ya existe en FTP
     (idempotencia — re-correr el comando no duplica).
   - **Upload + update** dentro de `DB::transaction()` por chunk. Si el
     upload retorna false o el update falla, la transacción hace
     rollback y la fila queda intacta.
4. Loguea cada skip/failure estructurado (`backfill.skip`,
   `backfill.content_resolve_failed`, `backfill.upload_or_update_failed`,
   etc) en `storage/logs/laravel.log`.
5. Emite stats line al terminar y exit code:
   - `0` si `failed == 0`.
   - `1` si hubo al menos una failure (los successes del batch siguen
     commiteados).

Output ejemplo:

```text
Backfilling product images... (24 encontrados)
  24/24 [============================] 100%
[backfill] processed=24 uploaded=20 skipped=0 failed=4
```

En este caso: 20 migradas, 4 con error (investigar logs por
`image_id` para cada failure). Las 20 exitosas están commiteadas.

### 8.4. Verificación post-backfill

Después de la corrida, validar que el backfill quedó consistente:

1. **Segundo dry-run debe dar todo en cero** (idempotencia):

   ```bash
   php artisan assets:backfill-images --dry-run --tenant=panda-estampados
   # Esperado: processed=N uploaded=0 skipped=N failed=0
   ```

2. **Cero filas con `image_url` fuera de la convención:**

   ```bash
   php artisan tinker --execute='dump(App\Models\Catalog\ProductImage::where("image_url", "NOT LIKE", "tenants/%")->count());'
   ```

   Debe devolver `0` para los tenants migrados.
3. **Cero logs `asset.path.malformed` post-flip:** una vez flipeado
   `path_validation.strict => true` (§8.5), el `AssetController` debe
   emitir 0 warnings de paths malformados (los pocos pre-existentes se
   aceptan vía whitelist explicita — ver §6.2).
4. **Spot-check visual de la PDP** en `https://panda-estampados.test/p/{slug}`
   de algunos productos — las imágenes deben servirse via el proxy del
   `AssetController` sin 404.

### 8.5. Flip strict mode (post-backfill)

Una vez verificado §8.4 y con un periodo de gracia de **24h** monitoreando
logs `asset.path.malformed`:

1. En `backend/config/assets.php` (o via env en `.env`):

   ```php
   'path_validation' => [
       'strict' => true,   // ← flip
   ],
   'url_signing' => [
       'required' => true, // ← flip (default Fase 6)
       ...
   ],
   ```

2. Reiniciar workers (queue workers, octane si aplica) para que el
   nuevo flag se cargue.
3. Monitorear `storage/logs/laravel.log` filtrando por
   `asset.path.malformed` y `asset.not_found` durante 24h. El rate debe
   ser ~0 (los pocos falsos positivos se aceptan vía whitelist
   documentada en §6.2).
4. Si el frontend no estaba firmando URLs previamente, deployar el
   cambio de frontend en el **mismo release** — sino los assets vuelven
   403 en seco.

### 8.6. Rollback procedure

Si la ejecución falla a mitad de camino:

1. **Por chunk:** cada upload + update está dentro de `DB::transaction()`.
   Si el FTP disk falla o el update tira excepción, el chunk se
   rollbacka — el `image_url` queda intacto en la DB.
2. **Restore de DB:** si el snapshot pre-flight (§8.1.4) detecta
   corrupción, restore atómico vía snapshot.
3. **Limpieza de FTP:** los archivos subidos durante la corrida fallida
   quedan huérfanos en FTP. Para borrarlos:

   ```bash
   php artisan tinker --execute='dump(Storage::disk("ftp")->delete("tenants/panda-estampados/products/camiseta-oversized/abc123def456.webp"));'
   ```

   O un script que recorra el log del backfill y borre los `storage_key`
   reportados como `uploaded=1` antes del rollback. En la práctica, los
   huérfanos ocupan espacio despreciable (32 productos × ~200KB = ~6MB)
   y se pueden dejar hasta el siguiente deploy.

---

## 9. Referencias

- `backend/app/Console/Commands/BackfillProductImagesCommand.php` —
  comando Artisan que ejecuta el procedimiento de §8.
- `backend/tests/Feature/Console/BackfillProductImagesCommandTest.php`
  — tests del comando (happy paths, dry-run, idempotencia, lock,
  confirmation).
- `backend/app/Services/Assets/StorageKeyDeriver.php` — derivador de
  storage keys usado por el `AssetController` (read) y el comando de
  backfill (write).
- `backend/config/filesystems.php` — definición de los 4 disks
  (`local`, `public`, `s3`, `ftp`).
- `backend/config/assets.php` — flags `path_validation.strict`,
  `url_signing.required`, cache store/TTL.
- `backend/config/brand.php` — paths default de categoría C.
- `backend/app/Models/Stores/StoreTheme.php` — modelo que guarda los
  paths de categoría A.
- `backend/database/migrations/stores/2026_08_02_000002_create_store_themes_table.php`
  — schema de la tabla que guarda los paths de categoría A.
- `backend/database/migrations/2026_06_28_193338_create_media_table.php` —
  tabla `media` (Spatie MediaLibrary) para uploads asociados a modelos.
- `docs/architecture/migration-pattern.md` — patrón de migrations
  universal + extension table (Fase 4).
- `docs/architecture/multi-store.md` — qué assets se comparten vs se
  separan entre Panda y Kitsune.

---

## 9. Final state (post Fase 6)

Después de las seis fases del image architecture plan, el estado
del codebase es:

- `StorageKeyDeriver` y `UrlSigner` puros con tests unitarios
  exhaustivos (Fases 2-3).
- `AssetController` con soft-mode validation, HMAC signing gated
  por config, cache config-driven con fallback, y 404 enumeration
  logging estructurado (Fases 2-3).
- `assets:backfill-images` Artisan command operacional con
  `--dry-run`, `--chunk`, `--tenant` y `--force` (Fase 5). El user
  lo corre manualmente con `--dry-run` primero para validar.
- Pattern de migraciones formalizado con decision tree para datos
  tenant-specific (Fase 4, doc aparte).
- Migration #2 (`convert_shipping_zones_base_cost_to_cents`)
  refactorizada con guard en estado NUEVO para idempotencia (Fase 6).

### Decisiones diferidas (no en scope de Fase 6)

- **Flip `path_validation.strict` a `true`** en `config/assets.php`.
  El user corre esto post-backfill manual + monitorea logs
  `asset.path.malformed` por 24h. Mientras el flag esté en `false`,
  paths malformados se loguean pero se sirven igual (soft mode).
- **Flip `url_signing.required` a `true`** en `config/assets.php`.
  Requiere un cambio de contrato de API: el backend debe devolver
  signed URLs (`image_url` con `?expires=...&sig=...`) en lugar del
  storage key crudo. Defer hasta que el equipo decida si el signing
  vive en el response del API o en un middleware que wrappea en el
  controller.
- **Adoptar la tabla `media` (Spatie MediaLibrary)** — sigue siendo
  dead code al momento de escribir este doc (ningún modelo usa
  `HasMedia` o `InteractsWithMedia`). Decisión: NO adoptar.
  `StorageKeyDeriver` cubre la necesidad de storage keys sin la
  complejidad de la library de Spatie. Si en el futuro se requiere
  metadata custom por asset, crear tabla de extensión siguiendo
  §5 de `migration-pattern.md`.
- **Backfill para otras tablas** que tienen storage de imágenes
  (`product_store_images.image_url`, `stores.logo_path`,
  `store_themes.logo_path`). El patrón del comando
  `assets:backfill-images` se reusa con ajustes menores para
  apuntar a cada tabla.
- **Refactor de `imageSrcset` en `frontend/src/lib/product-image.ts`**
  para que respete el nuevo contrato (las nuevas URLs no tienen
  size tokens). El helper actual devuelve srcset vacío para URLs
  nuevas — degraded pero funcional.

### Cleanup log

- **Eliminado**: `fenderImageUrl()` en
  `frontend/src/data/product-detail-mock.ts` (líneas 790-798 + su
  docblock de 14 líneas). Reemplazado por `assetUrl(mockAssetStorageKey(...))`
  en los 8 call sites.
- **Refactorizado**: `convert_shipping_zones_base_cost_to_cents.php`
  con guard invertido — early return si `base_cost_cents` ya existe
  (estado NUEVO). Mismo patrón en `down()`.
- **Sin tocar**: `frontend/src/lib/storage.ts` (placeholder vacío,
  no se elimina para no romper imports hipotéticos).
- **Sin tocar**: el resto del codebase. El grep post-Fase-6 dio
  cero referencias a `fenderImageUrl`, `rotateImage`, o variantes
  legacy del carousel.

---

## 10. Changelog

- **2026-10-17** — `§6.3 Runtime protections` agregado en Fase 3.
- **2026-10-17** — `§6.2 Drift` existente desde Fase 2.
- **2026-10-18** — `§9 Final state` agregado en Fase 6. Cleanup
  completo del legacy + refactor de migration #2.
- **2026-10-18** — `§11 Refactor estructural` agregado. Consolidación
  de las imágenes en `assets/` raíz del repo (single source of truth).
- **2026-10-19** — Productos reubicados a `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/`
  (per-tenant literal). Estructura intermedia `_images/` +
  `_manifest/` evaluada, committeada, revertida y descartada — el journey
  queda registrado en §12 como decision record. Detalles de la Opción C
  en el README de `assets/`.

---

## 11. Refactor estructural — consolidación en `assets/`

### Contexto

Antes del refactor (Q4 2026), las imágenes del proyecto vivían en
3 ubicaciones distintas con organizaciones inconsistentes:

- `frontend/public/images/` — assets de dev servidos por Astro
  (productos, branding, ilustraciones del framework).
- `brand-assets/` — staging area con Artisan command que sincronizaba
  al FTP (logos, favicons, social icons).
- `backend/public/images/stores/` — gitignored, regenerado por algún
  proceso (fantasma, nadie leía).

Cobertura inconsistente entre los 4 tenants históricos. Duplicados
entre `frontend/public/images/amico/auth/*.svg` y `brand-assets/frontend/`
(los mismos archivos en dos lugares). El tenant
`instrumentos-musicales-la-voz` (primer cliente real según el plan
original del image architecture) no tenía carpeta de assets en ningún
lado.

### Estructura objetivo

Después del refactor:

```text
AtlasCommerce/
├── backend/
├── frontend/
├── database/
├── assets/                      ← ÚNICA fuente de verdad para imágenes
│   ├── branding/                # Logos + favicons por tenant
│   │   ├── panda-estampados/
│   │   ├── kitsune/
│   │   ├── mm-boutique/
│   │   ├── morales-store/
│   │   └── instrumentos-musicales-la-voz/   (pendiente de archivos)
│   ├── products/                # Imágenes de producto (Fender/Gibson/etc.)
│   │   └── {brand-slug}/{product-slug}/{variant}/
│   ├── frontend/                # Ilustraciones del sistema
│   │   ├── amico/auth/*.svg     # (era brand-assets/frontend/)
│   │   └── favicon/             # Favicons del sitio (browser auto-discover)
│   └── shared/                  # Cross-tenant shared
│       └── icons/social/
└── docker/
```

### Cambios por archivo

| Antes | Después |
|---|---|
| `brand-assets/store/{slug}/{logo,favicon}.webp` | `assets/branding/{slug}/{logo,favicon}.webp` |
| `brand-assets/frontend/amico/auth/{file}.svg` | `assets/frontend/amico/auth/{file}.svg` |
| `brand-assets/shared/icons/social/*.webp` | `assets/shared/icons/social/*.webp` |
| `frontend/public/images/products/{slug}/{variant}/{file}.webp` | `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/{file}.webp` (per-tenant literal desde Oct 2026 — ver §12) |
| `frontend/public/images/tenants/panda-estampados/branding/{file}.webp` | (eliminado — duplicado de `assets/branding/panda-estampados/`) |
| `frontend/public/images/amico/auth/{file}.svg` | (eliminado — duplicado de `assets/frontend/amico/auth/`) |
| `frontend/public/apple-touch-icon.png` | `assets/frontend/favicon/apple-touch-icon.png` (con symlink) |
| `frontend/public/favicon.ico` | `assets/frontend/favicon/favicon.ico` (con symlink) |
| `frontend/public/favicon.svg` | `assets/frontend/favicon/favicon.svg` (con symlink) |

### Acceso en dev

El frontend consume los assets vía symlink
`frontend/public/assets → ../../assets/`. Cualquier URL del estilo
`/assets/{path}` la sirve Astro directamente desde la raíz de
`assets/`.

El backend consume los assets vía filesystem disk `local-assets`
(configurado en `backend/config/filesystems.php` con root apuntando
a `assets/`). Sirve como fallback cuando el FTP no está disponible
y como source para el Artisan `assets:sync-from-public` que migra
los assets al FTP en producción.

### Acceso en producción

El Artisan `php artisan assets:sync-from-public` (renombrado de
`brand-assets/` a `assets/`) lee la nueva estructura y sube al FTP
siguiendo el mismo mapping que antes (ver README de `assets/` para
detalle).

El frontend en producción consume assets vía el AssetController
backend (`/api/v1/storefront/assets/{path}`) que lee del FTP disk.
El storage key devuelto por la API es `tenants/{slug}/products/{slug}/{hash}.{ext}`
para productos o `tenants/{slug}/branding/{file}.webp` para branding.

### Limpieza aplicada

- `brand-assets/` eliminado, contenido migrado a `assets/`.
- `frontend/public/images/` eliminado, contenido migrado a `assets/`.
- Duplicados entre ubicaciones eliminados.
- Tenant `instrumentos-musicales-la-voz` agregado al `TenantSlug` type
  en `frontend/src/data/tenants.ts` con carpeta placeholder en
  `assets/branding/instrumentos-musicales-la-voz/`.
- Mock data (`product-detail-mock.ts`, `la-voz-mock.ts`, `products.ts`)
  y páginas Astro actualizadas con paths al prefijo `/assets/`.
- Artisan command `assets:sync-from-public` actualizado para leer
  de `assets/` en vez de `brand-assets/`.
- `filesystems.php` extendido con disk `local-assets`.

### Follow-ups pendientes

- **Subir imágenes reales** de La Voz Instrumentos a
  `assets/branding/instrumentos-musicales-la-voz/` (logo, favicon).
- **Subir imágenes faltantes** de productos La Voz (bajos, amps, PA,
  mics) que están como placeholders apuntando a Stratocaster.
- **Re-sync al FTP** después del refactor — ejecutar
  `php artisan assets:sync-from-public` en cada instancia (dev/prod).
- **Auditar páginas Astro** que referencien paths hardcoded
  (no quedan según `grep /images/products/` en `src/`, pero podría haber
  paths en `.css` o `.scss`).

---

## 12. Decision record — per-tenant product images (Oct 2026)

### Contexto

Después del refactor de §11, `assets/products/` quedó como
**una sola carpeta compartida** donde vivían los webps de Fender y
Gibson mezclados, independientemente del tenant que los vendía. En la
BD el modelo multi-tenant ya estaba resuelto vía `product_stores`
(productos globales con publicación per-tenant), pero el filesystem
no reflejaba el ownership claro de cada dataset.

Se necesitaba una decisión arquitectónica: ¿per-tenant literal en
el filesystem, o estructura global con manifests?

### Opciones evaluadas

| Opción | Estructura filesystem | Pros | Contras |
|---|---|---|---|
| **A. Status quo** | `assets/products/{brand-slug}/{product-slug}/{variant}/` | Simple, sin abstracción | No refleja ownership per-tenant en el filesystem |
| **B. Global + manifests** | `assets/products/_images/{brand}/{product}/{variant}/` + `assets/products/_manifest/{tenant}.json` | Webps viven una vez (no duplicar), manifests declaran qué vende cada tenant | Nivel extra de indirección, los manifests añaden ruido a PRs sin imagenes |
| **C. Per-tenant literal** | `assets/products/{tenant-slug}/{brand-slug}/{product-slug}/{variant-slug}/` | Refleja ownership claro en filesystem, `find` y `grep` triviales, una carpeta por tenant | Si 2 tenants comparten 1 producto, hay duplicación de webps |

### Cronología del journey

| Fecha | Evento | Commit |
|---|---|---|
| 2026-10-17 | Commit inicial Fase 6 (cleanup legacy Fender URLs) — `fenderImageUrl()` removido del frontend | varios (incluido `cc9d24f4~1`) |
| 2026-10-19 | Backup local de `gibson-images/` (LFS 4.6 GB / quota GitHub 1 GB excedida — preservado en `~/.local/atlascommerce-archive/gibson-images/`) | `ad74103e` |
| 2026-10-19 | Remoción de productos Fender del catálogo (4 productos respaldados en `~/.local/atlascommerce-archive/fender-products/`) | `ed3b6953` |
| 2026-10-19 | **Opción B committeada**: `assets/products/_images/ + _manifest/` con 5 manifests (4 vacíos + 1 con 15 productos de La Voz) | `cc9d24f4` |
| 2026-10-19 | **Revert de Opción B** (decision refinada) — el revert fue incompleto y borró los 160 webps Fender del filesystem, recuperados con `git checkout cc9d24f4 -- assets/products/_images/fender/` | `bcd80680` |
| 2026-10-19 | **Opción C aplicada**: estructura per-tenant literal, las 5 carpetas de tenant existentes con su `.gitkeep` (4 vacías) + La Voz con 15 productos completos | este refactor |

### Decisión final: Opción C

**Por qué ganó Opción C** (a pesar de la duplicación teórica):

1. **El modelo multi-tenant ya tiene `product_stores`** en la BD para
   resolver la publicación per-tenant en runtime. El filesystem no
   necesita duplicar esa lógica — solo refleja ownership claro.
2. **La duplicación solo ocurre si 2 tenants comparten 1 producto**.
   Hoy (Oct 2026) eso no pasa — cada tienda tiene su catálogo único
   (Panda estampados, Kitsune anime, MM vestidos, Morales ?, La Voz
   instrumentos musicales).
3. **La simplicidad del filesystem** (un nivel menos, sin manifests)
   hace que `find assets/products/` y `grep` sean triviales para
   auditorías.
4. **El costo de migrar a Opción B más adelante** es barato si el
   caso "compartir producto entre tenants" se materializa: un
   `git mv` + reescribir 5 manifests (~30 minutos).

### Trade-off aceptado (documentado)

> Si en el futuro 2 tenants comparten un mismo producto, se duplicarán
> los webps. En ese momento el costo de migración a opción B es un
> `git mv` + reescribir manifests. Riesgo aceptado: bajo (escenario
> "Panda vende cuerdas musicales" es plausible pero no inminente).

### Por qué descartamos la Opción B después de committearla

La lógica original detrás de Opción B era evitar duplicación futura.
Pero descubrimos durante el commit que:

- Los manifests vacíos son **ruido en PRs**: un developer que quiere
  agregar una imagen tiene que entender el modelo dual (imágenes vs
  manifests) — fricción innecesaria.
- El `_images/` con su prefijo underscore (convención de "directorio
  reservado/interno") confunde a quien hace `ls assets/products/` —
  parece "magia" en lugar de "datos reales".
- El refactor revert + reset demostró que Opción B era sobre-ingeniería
  para el caso de uso actual.

La Opción C es lo que siempre quisimos decir con per-tenant; el desvío
por B fue útil para verificar la decisión comparando ambas.

### Estado final del filesystem

```text
assets/products/
├── panda-estampados/.gitkeep                       (vacío)
├── kitsune/.gitkeep                                (vacío)
├── mm-boutique/.gitkeep                            (vacío)
├── morales-store/.gitkeep                          (vacío)
└── instrumentos-musicales-la-voz/                  (TIENE productos)
    ├── gibson/                                     (11 productos)
    └── fender/                                     (4 productos)
```

Total webps: **241** (81 Gibson + 160 Fender) en `instrumentos-musicales-la-voz/`.
Las otras 4 carpetas existen (con `.gitkeep`) pero vacías — explícitamente
declaran que esos tenants aún no venden productos musicales.

### Contrato hacia adelante

Si un nuevo tenant empieza a vender productos (ej: "Panda Estampados
abre sección Musical" o "Kitsune abre sección Merch"), el flujo es:

1. `mkdir -p assets/products/{nuevo-tenant}/{brand}/{product}/{variant}/`
2. Optimizar webps multi-size (400, 800, 1200, 1600).
3. Commit + PR.
4. Agregar entry a `backend/database/seeds/products.json`.
5. `php artisan db:seed`.
6. `php artisan assets:sync-from-public --store={nuevo-tenant}` para
   subir al FTP hasheado vía `StorageKeyDeriver`.

El `StorageKeyDeriver` no sabe nada del filesystem layout — produce el
mismo storage key `tenants/{slug}/products/{product-slug}/{hash12}.webp`
independientemente de cómo los webps se organizan en el repo.

### Refs

- `assets/README.md` — documentación operativa de la estructura.
- `backend/app/Services/Assets/StorageKeyDeriver.php` — derivador puro
  que el seeder invocará en Fase 3 del plan v3.
- `backend/app/Console/Commands/SyncAssetsFromPublicCommand.php` —
  puente repo→FTP que extenderemos en Fase 4 del plan v3.
- `odd/tasks/asset-pipeline-refactor.md` — feature document ODD con el
  plan completo de fases (0 a 6).
