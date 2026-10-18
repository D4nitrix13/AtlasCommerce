<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD031 -->

# Plan: Image Architecture — Fase 5 (Backfill imágenes existentes)

> **Status**: en ejecución. Branch base: `master` HEAD `ff0309f0` (Fases 2-4 mergeadas).
>
> **Scope**: crear `assets:backfill-images` Artisan command que re-hashea + re-sube imágenes existentes al nuevo naming convention (content-hash via `StorageKeyDeriver`), actualiza `product_images.image_url` con la nueva storage key, y soporta `--dry-run` para validación previa. Documentar procedimiento operativo. NO se ejecuta automáticamente — user corre después de revisar.
>
> **Engram mirror**: `odd/image-architecture-phase-5/tasks`

---

## 0. Contexto

Fases 2-4 dejaron:
- `StorageKeyDeriver` con `derive()` determinístico (SHA-256 de contenido, 12 chars hex, formato `tenants/{slug}/{collection}/{entity}/{hash}.{ext}`).
- `AssetController` con soft-mode validation (loguea warnings, no rechaza) + URL signing opcional (default off) + cache config-driven.
- Doc formal de migration pattern (Fase 4) con decision tree para datos tenant-specific.
- `product_images.image_url` columna `string` libre con valores actuales mezclados: CDN URLs (Fender/Gibson), paths legacy (`/images/products/...`), y keys nuevas (las que ya fueron migradas).

Fase 5 cierra el ciclo: mover todas las imágenes existentes al nuevo formato, dejando la DB consistente con el contrato de Fase 2.

---

## 1. Decisiones de diseño

### D1 — Reemplazar `image_url` con la storage key (NO agregar columna nueva)

**Decisión**: el backfill REEMPLAZA `product_images.image_url` con la nueva storage key (ej. `tenants/panda-estampados/products/fender-player-telecaster/abc123def456.webp`). NO agrega columna `storage_key` separada.

**Rationale**:
- Functionality: una sola columna con significado claro (storage key) es más simple que dos columnas redundantes.
- Security: el nuevo formato cumple el regex validado por Fase 2 — `path_validation.strict` puede flipearse a `true` post-backfill.
- Performance: el hash-based key es CDN-friendly.
- Scalability: dedup automático (mismo contenido = misma key) reduce storage.

**Tradeoff aceptado**: se pierde el mapping al URL externo original (ej. `https://cdn.fender.com/...`). Si se necesita traceability, queda en `media.custom_properties` JSON si Spatie se adopta en el futuro, o en logs del backfill.

### D2 — Re-upload a nuestro FTP (NO solo actualizar la referencia)

**Decisión**: el comando descarga el contenido desde la fuente actual (HTTP si es URL externa, file read si es path local), lo sube al FTP disk via `Storage::disk('ftp')->put()`, y actualiza la referencia.

**Rationale**:
- Functionality: el asset queda bajo nuestro control — no dependemos de CDN externos que pueden cambiar formato o caer.
- Security: una vez en nuestro FTP, el `AssetController` aplica URL signing + rate limiting + logging.
- Performance: nuestras keys son CDN-cacheable; URLs externas no las podemos cachear.
- Scalability: una sola fuente de verdad (nuestro FTP).

**Tradeoff aceptado**: costo de storage temporal (descarga + upload). Para 32 productos seedeados (per memory), es despreciable.

### D3 — Comando Artisan con `--dry-run`, NO migración automática

**Decisión**: `php artisan assets:backfill-images {--dry-run} {--chunk=100} {--tenant=*}`. NO se ejecuta automáticamente en deploy.

**Rationale**:
- Functionality: el user corre el comando cuando está listo, con `--dry-run` primero para validar.
- Security: cambio destructivo de datos requiere revisión humana.
- Performance: comando se puede correr en background sin afectar deploy.
- Scalability: patrón replicable para futuros tenants.

---

## 2. Archivos a tocar

| Archivo | Acción |
|---|---|
| `backend/app/Console/Commands/BackfillProductImagesCommand.php` | **CREAR** — Artisan command con `--dry-run`, `--chunk`, `--tenant` |
| `backend/tests/Feature/Console/BackfillProductImagesCommandTest.php` | **CREAR** — tests del command con fixtures |
| `docs/architecture/assets-naming-convention.md` | **EXTENDER** — agregar §8 Backfill procedure |
| `odd/tasks/image-architecture-phase-5.md` | **CREAR** — este task file |

Sin tocar: `AssetController`, `StorageKeyDeriver`, modelos, migraciones existentes.

---

## T1 — Crear `BackfillProductImagesCommand`

**Signature**:
```php
protected $signature = 'assets:backfill-images
    {--dry-run : Preview changes without modifying data}
    {--chunk=100 : Number of rows to process per batch}
    {--tenant=* : Limit to specific tenant slug(s)}
    {--force : Skip confirmation prompt}';

protected $description = 'Re-hash + re-upload product images to new storage key format';
```

**Constructor con DI** (per AGENTS.md §3 — property promotion):
```php
public function __construct(
    public readonly StorageKeyDeriver $deriver,
    public readonly FilesystemManager $filesystem,
) {
    parent::__construct();
}
```

**`handle()` flow**:

1. Parsear options (`--dry-run`, `--chunk`, `--tenant`, `--force`).
2. Si NO `--dry-run` y NO `--force`: pedir confirmación interactiva (`$this->confirm(...)`).
3. Query `ProductImage::query()` con chunk:
   - Filtrar por tenant si se especificó (vía relación con store).
   - Excluir rows que ya tienen storage key válida (regex check via `deriver->validate()`).
4. Para cada row:
   a. Resolver source content:
      - Si `image_url` empieza con `http(s)://`: HTTP GET via `Http::get()`.
      - Si empieza con `/`: leer de `Storage::disk('local')->get()` (legacy local storage).
      - Si empieza con `tenants/` o `shared/`: SKIP (ya migrado).
      - Si falla la resolución: log warning, continuar.
   b. Compute SHA-256 hash del contenido.
   c. Derive nueva storage key:
      - Tenant slug: resolver vía `product.store` (relación Eloquent).
      - Collection: `products`.
      - Entity: `product.slug` (lowercase, kebab-case).
      - Extension: de la URL original (jpg/png/webp).
   d. Si NO `--dry-run`: upload via `Storage::disk('ftp')->put($key, $content)`.
   e. Si NO `--dry-run`: update `product_images.image_url = $key`.
   f. Incrementar stats (`processed`, `uploaded`, `skipped`, `failed`).
5. Log final con stats: `[backfill] processed=320 uploaded=300 skipped=15 failed=5`.
6. Exit code 0 si no failed, 1 si hubo failures.

**Output format** (progress bar via `$this->withProgressBar(...)`):
```
Backfilling product images...
 320/320 [============================] 100%
[backfill] processed=320 uploaded=300 skipped=15 failed=5
```

**Tests** (`BackfillProductImagesCommandTest.php`):
- `it_skips_already_migrated_images` — rows con storage key válida no se tocan.
- `it_uploads_external_url_to_ftp` — mock HTTP, verifica upload.
- `it_uploads_local_path_to_ftp` — Storage::fake local + ftp, verifica flujo.
- `it_replaces_image_url_with_new_storage_key` — verifica update en DB.
- `it_supports_dry_run_without_modifying_data` — con `--dry-run`, DB no cambia.
- `it_filters_by_tenant_when_specified` — `--tenant=panda-estampados` solo afecta ese tenant.
- `it_chunks_processing_for_large_datasets` — verifica que no se carga todo en memoria.
- `it_logs_failures_and_continues` — imagen con URL rota no rompe el batch.

---

## T2 — Documentar procedimiento en `assets-naming-convention.md`

Agregar **§8 Backfill procedure** con:

1. **Pre-flight checklist**:
   - Confirmar que Fases 2-4 están mergeadas en master.
   - Confirmar que `config/assets.php` tiene los flags correctos.
   - Confirmar que `StorageKeyDeriver::derive()` y `Storage::disk('ftp')` funcionan (test manual).
   - Backup de la DB (snapshot del tenant a migrar).

2. **Dry-run**:
   ```bash
   cd backend
   php artisan assets:backfill-images --dry-run --tenant=panda-estampados
   ```
   Output esperado: stats con `processed`, `skipped`, `failed`. NO debe haber `uploaded` en dry-run.

3. **Ejecución real**:
   ```bash
   php artisan assets:backfill-images --tenant=panda-estampados --chunk=50
   ```
   Prompt de confirmación (a menos que `--force`).

4. **Verificación post-backfill**:
   ```bash
   php artisan assets:backfill-images --dry-run --tenant=panda-estampados
   # Second run debe dar processed=0 uploaded=0 (todo ya migrado)

   php artisan tinker --execute='dump(App\Models\Catalog\ProductImage::where("image_url", "NOT LIKE", "tenants/%")->count());'
   # Debe ser 0
   ```

5. **Flip strict mode** (post-backfill):
   - En `config/assets.php`: `path_validation.strict => true` + `url_signing.required => true`.
   - Reiniciar workers.
   - Monitorear logs `asset.path.malformed` por 24h — debe ser ~0.

6. **Rollback procedure** (si falla):
   - El backfill NO modifica la tabla original hasta el final de cada chunk (transacción).
   - Backup de la DB permite rollback completo.
   - Las imágenes en FTP con keys nuevas se pueden dejar (no ocupan espacio significativo) o borrar via `Storage::disk('ftp')->delete($key)`.

**Commit**: `docs: documentar backfill procedure para product images`

---

## 3. Plan de ejecución

1. **T1** — command + tests (1-2 commits work-unit).
2. **T2** — docs (1 commit).
3. **NO ejecutar el backfill** — user corre cuando quiera.

Total esperado: 2-3 commits, ~400-500 líneas (command + tests + docs).

---

## 4. Setup de tests para el writer

El backend requiere Docker stack levantado. Containers del dev stack siguen arriba de Fases 2-3. Si necesitas re-crearlos:

```bash
cd docker/instances/development/panda-estampados-and-kitsune
HOST_UID=1000 HOST_GID=1000 docker compose --env-file environment/.env up -d postgres redis backend
```

Para tests del command, usar `Storage::fake(disk: 'ftp')` + `Storage::fake(disk: 'local')` + `Http::fake()` para no tocar FTP real ni red externa.

Cache usa Redis DB1 — `redis-cli -n 1 FLUSHALL` antes de tests que asumen cache vacío.

---

## 5. Riesgos

| Risk | Mitigation |
|---|---|
| HTTP fetch de CDN externo lento/falla | `Http::fake()` en tests + timeout configurable (default 10s) + log de failures + continuar batch |
| FTP disk no escribible en el ambiente de tests | `Storage::fake(disk: 'ftp')` para tests |
| Tenant slug ambiguity en queries | Filtrar por relación `product.store` con eager loading para evitar N+1 |
| Migración destruye image_url original antes de verificar upload | Update en transacción por chunk — rollback si upload falla |
| `Http::get()` re-descarga imágenes que ya están en storage local | Pre-check: si la key derivada del hash del path ya existe en FTP, skip con `processed but not re-uploaded` |
| Race condition si comando se corre dos veces en paralelo | Documentar: comando debe correr single-instance; lock via `Cache::lock('backfill-images', ttl: 600)` |

---

## 6. Follow-ups deferred

- Backfill para `product_store_images.image_url` — mismo patrón, comando separado o flag `--include-stores`.
- Backfill para `stores.logo_path` + `store_themes.logo_path` — branding assets, distinto comando.
- Adopción de Spatie MediaLibrary (dead code per Fase 2 audit) — decidir si wrapper propio o library.
- Flip a strict mode (Fase 6) requiere backfill 100% completo + monitoreo 24h.
