# Plan: Image Architecture — Fase 2 (StorageKeyDeriver + soft-mode wire-in)

> **Status**: en ejecución. Branch: `feat/assets-storage-key-deriver` (desde `master` HEAD `28d46c93`).
>
> **Scope**: crear `App\Services\Assets\StorageKeyDeriver` (puro, determinístico) + tests + wire **soft mode** al `AssetController` (log warning, NO rechaza — Fase 5 backfill antes de flippear a strict). Update del doc con fase boundaries. NO se hace: HMAC URL signing, cache config/TTL fix, 404 enumeration logging (todo eso = Fase 3).
>
> **Engram mirror**: `odd/image-architecture-phase-2/tasks`

---

## 0. Contexto y motivación

El audit READ-ONLY (obs #511 + exploración) reveló que la realidad difiere del plan original:

- ✅ FTP disk ya configurado (`filesystems.php:65-88`).
- ✅ `AssetController` ya existe, lee de FTP, cache Redis 24h, ETag/304, validación de tenant prefix.
- ❌ `StorageKeyDeriver` no existe.
- ❌ El controller no valida el formato del path (cualquier `tenants/{slug}/...` se sirve) — gap de enumeración.
- ❌ Cache hard-codeado sin fallback.
- ❌ URL signing (HMAC + expires) no implementado.

El user aprobó arrancar Fase 2. Inicialmente se eligió Opción A (solo la clase pura), pero esa entrega era simbólica (clase muerta sin wire-in). Pivot a **B con soft mode**: la clase se USA en el controller en modo observability (loguea warnings de paths malformados, NO rechaza). Esto nos da valor funcional real + base para que Fase 5 (backfill) pueda eventualmente flipear a strict mode sin riesgo de romper producción.

---

## 1. Archivos a tocar

| Archivo | Acción |
|---|---|
| `backend/app/Services/Assets/StorageKeyDeriver.php` | **CREAR** — clase pura con `derive()` + `validate()` + `extractComponents()` |
| `backend/tests/Unit/Services/Assets/StorageKeyDeriverTest.php` | **CREAR** — ≈15 tests (happy + failure paths) |
| `backend/config/assets.php` | **CREAR** (si no existe) o **EXTENDER** con `path_validation.strict` flag |
| `backend/app/Http/Controllers/Api/Storefront/AssetController.php` | **MODIFICAR** — wire soft-mode validation (loguear warning si path no matchea regex) |
| `backend/tests/Feature/Storefront/StorefrontAssetTest.php` | **EXTENDER** — 3-4 tests nuevos cubriendo soft-mode behavior |
| `docs/architecture/assets-naming-convention.md` | **UPDATE** — agregar §6 phased rollout + nota sobre drift FTP_ROOT/ETag |

Sin tocar: `filesystems.php`, `routes/`, migraciones, modelos.

---

## T1 — Crear `StorageKeyDeriver` (puro, determinístico)

**Decisiones de diseño**:

1. **Algoritmo**: SHA-256 completo del contenido binario (`hash('sha256', $content)`).
   - Contrato documentado: `substr(sha256(file), 0, 12)` (`docs/architecture/assets-naming-convention.md:84`).
   - 12 chars hex = 48 bits ≈ 2.81e14 combinaciones — anti-enumeración suficiente dentro de un tenant.
2. **Path patterns** (regex):
   - Tenant: `^tenants/(?P<slug>[a-z0-9-]{1,64})/(?P<collection>products|branding|marketing)/(?P<entity>[a-z0-9-]{1,128})/(?P<hash>[a-f0-9]{12})\.(?P<ext>webp|png|jpg|jpeg|svg)$`
   - Shared: `^shared/(?P<entity>[a-z0-9-]{1,128})/(?P<hash>[a-f0-9]{12})\.(?P<ext>webp|png|jpg|jpeg|svg)$`
3. **API pública**:
   - `derive(string $tenantSlug, string $collection, string $entity, string $content, string $extension): string`
   - `validate(string $path): bool`
   - `extractComponents(string $path): ?array` — helper para migration tooling futuro
4. **Sin I/O, sin globals, sin Storage facade** — clase pura.
5. **Named args** en todos los métodos (per AGENTS.md §1).
6. **`declare(strict_types=1)`**, sin `mixed`, PHPDoc explicando el "por qué".
7. **Constantes públicas**: `HASH_ALGO`, `HASH_LENGTH`, `EXTENSIONS`, `TENANT_PATH_PATTERN`, `SHARED_PATH_PATTERN`.

**Commit**: `feat(assets): add StorageKeyDeriver service (content-hash key + format validation)`

**Verificación**:
- `cd backend && vendor/bin/pint --dirty --format=agent` → exit 0
- `cd backend && php artisan test --compact --filter=StorageKeyDeriverTest` → exit 0 + todos los tests pasan

---

## T2 — Tests unitarios para `StorageKeyDeriver`

**Cobertura mínima** (per AGENTS.md: happy + failure + edge cases):

### `derive()`
- `it_produces_tenant_path_with_12_char_content_hash`
- `it_produces_deterministic_hash_for_same_content`
- `it_produces_different_hash_for_different_content`
- `it_supports_all_documented_extensions` (parametrizado)
- `it_rejects_invalid_extension` → `InvalidArgumentException`
- `it_rejects_empty_content` → `InvalidArgumentException`
- `it_rejects_invalid_slug_chars` → `InvalidArgumentException`
- `it_rejects_invalid_collection` → `InvalidArgumentException`

### `validate()`
- `it_accepts_well_formed_tenant_path`
- `it_accepts_well_formed_shared_path`
- `it_rejects_path_traversal` (`..` o `/` extra)
- `it_rejects_null_byte_injection`
- `it_rejects_uppercase_slug`
- `it_rejects_invalid_hash_length` (≠12 chars)
- `it_rejects_non_hex_hash_chars`
- `it_rejects_unsupported_extension`
- `it_rejects_empty_path`
- `it_rejects_absolute_path_starting_with_slash`

### `extractComponents()`
- `it_returns_tenant_components_for_tenant_path`
- `it_returns_shared_components_for_shared_path`
- `it_returns_null_for_invalid_path`

**Patrón**: mirror de `backend/tests/Unit/Support/MoneyTest.php` (PHPUnit attributes, named args, sin dependencias de DB).

**Commit**: incluido en T1 (mismo commit — clase sin tests no tiene sentido).

---

## T3 — Wire soft-mode validation en `AssetController`

**Problema**: `AssetController` sirve cualquier path bajo `tenants/{slug}/` o `shared/`. Si en producción hay paths válidos que no matchean el nuevo regex (porque vienen de seeders viejos o imágenes legacy), un reject rompe la PDP.

**Solución**: wire `StorageKeyDeriver::validate()` en modo **soft**: si el path no matchea el regex, **loguear warning estructurado** y **seguir sirviendo** sin rechazar. La strict-rejection se activa cuando `config('assets.path_validation.strict') === true` (default `false` en Fase 2; se flipea a `true` post-Fase-5-backfill en Fase 6).

**Cambios**:

1. **Si `config/assets.php` no existe**, crearlo:
   ```php
   <?php
   
   declare(strict_types=1);
   
   return [
       'path_validation' => [
           'strict' => env(key: 'ASSETS_PATH_VALIDATION_STRICT', default: false),
       ],
   ];
   ```
2. **Si ya existe**, agregar la key `path_validation.strict` respetando el estilo existente.
3. **`AssetController`** — agregar después del `pathBelongsToTenant()` check (≈ línea 80):
   ```php
   if (! $this->storageKeyDeriver->validate(path: $normalizedPath)) {
       if (config(key: 'assets.path_validation.strict') === true) {
           abort(code: 403, message: 'Malformed asset path.');
       }
       
       logger()->warning(message: 'asset.path.malformed', context: [
           'hash' => hash(algo: 'sha256', data: $normalizedPath),
           'tenant_slug' => $this->tenantSlug,
           'strict_mode' => false,
       ]);
   }
   ```
4. **Inyectar `StorageKeyDeriver` por constructor** (per AGENTS.md §3 — constructor property promotion; `__construct()` vacío no permitido).
5. **NO tocar** la lógica de cache, ETag, headers, ni el orden de las validaciones existentes.

**Tests nuevos en `StorefrontAssetTest`** (3-4 casos):
- `it_logs_warning_when_path_does_not_match_naming_convention_in_soft_mode` — verifica log
- `it_rejects_with_403_in_strict_mode_when_path_malformed` — flip config + verifica reject
- `it_serves_correctly_in_soft_mode_when_path_malformed` — verifica que igual se sirve el binario

**Commit**: `feat(assets): wire soft-mode path validation in AssetController`

**Verificación**:
- `cd backend && vendor/bin/pint --dirty --format=agent` → exit 0
- `cd backend && php artisan test --compact --filter='StorageKeyDeriverTest|StorefrontAssetTest'` → exit 0 + todos pasan

---

## T4 — Update `docs/architecture/assets-naming-convention.md`

**Problema**: el doc tiene drift con el código (FTP_ROOT dice `/srv/ftp/images` pero config dice `/`; ETag dice "mtime+size" pero código usa `md5(content)`). No menciona qué fase cubre qué.

**Solución**: agregar **§6 Phased rollout** que diga explícitamente:

| Fase | Scope | Estado |
|---|---|---|
| 2 (NOW) | `StorageKeyDeriver` + soft-mode validation en `AssetController` | en ejecución |
| 3 | Wire `validate()` strict (post-backfill) + HMAC signed URLs + cache config-driven con fallback + 404 enumeration logging | pendiente |
| 4 | Pattern de migrations (universal + extension table) | pendiente |
| 5 | Backfill de imágenes existentes al nuevo formato | pendiente |
| 6 | Flip soft → strict + cleanup legacy `fenderImageUrl()` | pendiente |

Y agregar nota sobre drift FTP_ROOT/ETag con links a las líneas de código real.

**Commit**: `docs(assets): clarify phased rollout + document config drift`

**Verificación**:
- `git diff --stat docs/architecture/assets-naming-convention.md` → cambios mínimos y legibles.

---

## 5. Plan de ejecución (orden)

1. **T1+T2** juntos (clase + tests en un solo commit).
2. **T3** después (wire al controller — depende de T1).
3. **T4** último (docs alineados al cambio).
4. Reporte al user.

Total esperado: 3 commits work-unit, ~350-400 líneas (clase + tests + controller wire-in + docs).

---

## 6. Riesgos

| Risk | Mitigation |
|---|---|
| Drift entre doc y código se amplifica | T4 lo documenta; Fase 3 corregirá config-side |
| Soft mode emite muchos warnings (paths legacy abundan) | Esperable y bueno: el log es la señal para priorizar el backfill de Fase 5 |
| Constructor change en `AssetController` rompe DI del container | Laravel auto-resuelve; verificar con `php artisan route:list` después |
| Tests del controller existentes rompen por el wire-in | Ninguno path válido existente matchea el regex → todos pasan; soft mode es no-op para paths válidos |

---

## 7. Follow-ups deferred (no en este plan)

- Wire strict mode (Fase 3)
- HMAC URL signing (Fase 3)
- Cache config + TTL fix (Fase 3)
- 404 enumeration logging (Fase 3)
- Backfill de imágenes existentes (Fase 5)
- Cleanup legacy (Fase 6)
- LP Supreme Blueberry Burst seeder (pre-arquitectura)