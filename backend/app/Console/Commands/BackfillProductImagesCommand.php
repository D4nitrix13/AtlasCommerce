<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\ProductImage;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Backfill de `product_images.image_url` al formato canónico de la
 * convención de Fase 2 (ver
 * `docs/architecture/assets-naming-convention.md` §2 y §6).
 *
 * Recorre las filas de `product_images` que NO matchean la convención
 * (URLs externas, paths legacy de local disk) y para cada una:
 *
 *   1. Resuelve el contenido binario desde la fuente actual (HTTP GET
 *      para URLs externas, `Storage::disk('local')` para paths legacy).
 *   2. Deriva la nueva storage key via `StorageKeyDeriver::derive()`
 *      con `tenant_slug` (de `product.store`), `collection=products`,
 *      `entity=product.slug`, `extension` extraída del path original.
 *   3. Verifica que la key no exista ya en el disk `ftp` (idempotencia).
 *   4. Sube el contenido al disk `ftp` y actualiza `image_url` con la
 *      nueva key — ambas operaciones dentro de una transacción por
 *      chunk para rollback si algo falla.
 *
 * Es **idempotente**: re-correrlo no duplica uploads ni corrompe la DB.
 *
 * El comando usa `--dry-run` para preview sin escribir, `--force` para
 * saltear el prompt de confirmación, `--chunk=N` para batch size, y
 * `--tenant=slug` para limitar a uno o más tenants. Un lock de cache
 * (TTL 600s) previene ejecuciones concurrentes.
 *
 * Uso:
 *   php artisan assets:backfill-images --dry-run --tenant=panda-estampados
 *   php artisan assets:backfill-images --tenant=panda-estampados --chunk=50
 *   php artisan assets:backfill-images --force --tenant=kitsune
 */
final class BackfillProductImagesCommand extends Command
{
    /**
     * Signature del comando.
     *
     * @var string
     */
    protected $signature = 'assets:backfill-images
        {--dry-run : Preview changes without modifying data}
        {--chunk=100 : Number of rows to process per batch}
        {--tenant=* : Limit to specific tenant slug(s) (repeatable)}
        {--force : Skip confirmation prompt}';

    /**
     * Descripción del comando (visible en `php artisan list`).
     *
     * @var string
     */
    protected $description = 'Re-hash + re-upload product images to new storage key format';

    /**
     * Cache lock key para prevenir ejecuciones concurrentes.
     */
    private const LOCK_KEY = 'backfill-images';

    /**
     * TTL del cache lock (segundos). Backfill de ~1000 imágenes debería
     * terminar muy por debajo de este valor; 600s es margen defensivo.
     */
    private const LOCK_TTL_SECONDS = 600;

    /**
     * Timeout HTTP para descargar imágenes de CDNs externos (segundos).
     * Default del cliente HTTP de Laravel es 30s — Fase 5 baja a 10s
     * porque un CDN que tarda más de eso no merece el slot de red.
     */
    private const HTTP_TIMEOUT_SECONDS = 10;

    public function __construct(
        public readonly StorageKeyDeriver $deriver,
        public readonly FilesystemManager $filesystem,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $chunkSize = max(1, (int) $this->option(key: 'chunk'));
        $tenantOption = (array) $this->option(key: 'tenant');
        $tenants = array_values(array_filter(
            array: array_map(callback: 'strval', array: $tenantOption),
            callback: fn (string $value): bool => $value !== '',
        ));
        $isForce = (bool) $this->option(key: 'force');

        // Confirmación interactiva salvo --dry-run (no escribe) o --force.
        if (! $isDryRun && ! $isForce) {
            $confirmed = $this->confirm(
                question: 'Vas a modificar product_images.image_url en la DB. Continuar?',
                default: false,
            );

            if (! $confirmed) {
                $this->warn(string: 'Operacion cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        // Lock single-instance para evitar race conditions. Cache::lock
        // devuelve false si el lock no se adquirio (otro proceso corriendo).
        $lock = Cache::lock(name: self::LOCK_KEY, seconds: self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->error(string: 'Otro proceso de backfill ya esta corriendo (lock activo). Saliendo.');

            return self::FAILURE;
        }

        try {
            return $this->runBackfill(isDryRun: $isDryRun, chunkSize: $chunkSize, tenants: $tenants);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ejecutar el backfill con stats + progress bar.
     *
     * @param  array<int, string>  $tenants  Slugs de tenant para filtrar (vacio = todos).
     */
    private function runBackfill(bool $isDryRun, int $chunkSize, array $tenants): int
    {
        $stats = [
            'processed' => 0,
            'uploaded' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $query = ProductImage::query()
            ->with(relations: ['product']);

        // Workaround: `Product::stores()` está rota en este repo — el
        // belongsToMany default usa `product_store_id` como foreign
        // pivot key pero la tabla `product_stores` no tiene esa columna
        // (ver ProductController.php:376). Resolvemos el filtro de
        // tenant con un JOIN directo.
        if ($tenants !== []) {
            $productIds = DB::table('product_stores')
                ->join('stores', 'stores.id', '=', 'product_stores.store_id')
                ->whereIn(column: 'stores.slug', values: $tenants)
                ->pluck(column: 'product_stores.product_id')
                ->unique()
                ->values()
                ->all();

            if ($productIds === []) {
                $this->info(string: 'No hay productos en los tenants especificados. Saliendo.');

                return self::SUCCESS;
            }

            $query->whereIn(column: 'product_id', values: $productIds);
        }

        // Pre-fetch bulk de tenant slugs por product_id (evita N+1 en
        // el loop). Una sola query SQL para todo el dataset, no por
        // chunk ni por imagen.
        $productIds = (clone $query)->pluck(column: 'product_id')->unique()->values()->all();
        $tenantSlugByProduct = $productIds === []
            ? []
            : $this->resolveTenantSlugsBulk(productIds: $productIds);

        $total = (clone $query)->count();
        $this->info(string: sprintf('Backfilling product images... (%d encontrados)', $total));

        $this->withProgressBar(
            $query->lazyById(chunkSize: $chunkSize),
            function (ProductImage $image) use ($isDryRun, &$stats, $tenantSlugByProduct): void {
                $stats['processed']++;
                $tenantSlug = $image->product_id !== null && is_int(value: $image->product_id)
                    ? ($tenantSlugByProduct[$image->product_id] ?? null)
                    : null;
                $this->processImage(
                    image: $image,
                    isDryRun: $isDryRun,
                    stats: $stats,
                    tenantSlug: $tenantSlug,
                );
            },
        );

        $this->newLine();
        $this->info(string: sprintf(
            '[backfill] processed=%d uploaded=%d skipped=%d failed=%d',
            $stats['processed'],
            $stats['uploaded'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolver tenant_slug para cada product_id en bulk.
     *
     * Si un producto está publicado en múltiples tiendas (multi-tenant),
     * usamos la primera (orden de insertion en `product_stores`). El
     * backfill escribe el archivo UNA vez — si el mismo binario vive
     * en varias tiendas, las demás se servirán via el proxy que ya
     * resuelve el slug desde `store.domain`.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, string>
     */
    private function resolveTenantSlugsBulk(array $productIds): array
    {
        $rows = DB::table('product_stores')
            ->join('stores', 'stores.id', '=', 'product_stores.store_id')
            ->whereIn(column: 'product_stores.product_id', values: $productIds)
            ->orderBy(column: 'product_stores.id', direction: 'asc')
            ->select('product_stores.product_id', 'stores.slug')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $slug = (string) $row->slug;

            if (! isset($map[$productId]) && $slug !== '') {
                $map[$productId] = $slug;
            }
        }

        return $map;
    }

    /**
     * Procesar una imagen individual.
     *
     * Fases:
     *   - skip si ya tiene storage key valida (validate()).
     *   - resolver contenido desde URL externa o path legacy de local.
     *   - resolver tenant_slug + product.slug + extension.
     *   - derivar nueva storage key.
     *   - en --dry-run: contar como processed y salir sin escribir.
     *   - pre-check de existencia en FTP (idempotencia).
     *   - subir + update dentro de transaccion por chunk.
     *
     * Cualquier excepcion o fallo se loguea estructuradamente y se
     * cuenta como `failed`. La fila siguiente (o) sigue procesandose.
     *
     * @param  array{processed: int, uploaded: int, skipped: int, failed: int}  $stats  (por referencia)
     */
    private function processImage(
        ProductImage $image,
        bool $isDryRun,
        array &$stats,
        ?string $tenantSlug,
    ): void {
        $imageUrl = (string) $image->image_url;

        if ($imageUrl === '') {
            $stats['skipped']++;
            $this->logSkip(image: $image, reason: 'empty_image_url');

            return;
        }

        // Ya migrado: validate() devuelve true para paths canonicos.
        if ($this->deriver->validate(path: $imageUrl)) {
            $stats['skipped']++;
            $this->logSkip(image: $image, reason: 'already_migrated');

            return;
        }

        try {
            $content = $this->resolveContent(imageUrl: $imageUrl);
        } catch (Throwable $e) {
            $stats['failed']++;
            Log::warning(message: 'backfill.content_resolve_failed', context: [
                'image_id' => $image->id,
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($content === null) {
            // Formato no soportado (no http, no /, no tenants/, no shared/).
            $stats['failed']++;
            Log::warning(message: 'backfill.unresolvable_source', context: [
                'image_id' => $image->id,
                'image_url' => $imageUrl,
            ]);

            return;
        }

        $product = $image->product;

        if ($tenantSlug === null || $tenantSlug === '' || $product === null || $product->slug === '') {
            $stats['failed']++;
            Log::warning(message: 'backfill.missing_tenant_or_product_slug', context: [
                'image_id' => $image->id,
                'product_id' => $product?->id,
            ]);

            return;
        }

        $extension = $this->extractExtension(imageUrl: $imageUrl);

        if ($extension === null) {
            $stats['failed']++;
            Log::warning(message: 'backfill.unsupported_extension', context: [
                'image_id' => $image->id,
                'image_url' => $imageUrl,
            ]);

            return;
        }

        try {
            $newKey = $this->deriver->derive(
                tenantSlug: $tenantSlug,
                collection: 'products',
                entity: $product->slug,
                content: $content,
                extension: $extension,
            );
        } catch (Throwable $e) {
            $stats['failed']++;
            Log::warning(message: 'backfill.key_derivation_failed', context: [
                'image_id' => $image->id,
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($isDryRun) {
            // En dry-run no se sube ni se actualiza la DB. La fila
            // queda contabilizada en `processed` pero `uploaded` se
            // mantiene en 0 — ver doc §8 dry-run section.
            return;
        }

        // Pre-check de idempotencia: si la key ya existe en el FTP,
        // skip con `skipped` (no incrementa `failed` ni `uploaded`).
        try {
            $exists = $this->filesystem->disk(name: 'ftp')->exists(path: $newKey);
        } catch (Throwable) {
            // exists() puede tirar en algunos drivers si el path padre
            // no existe. Tratamos como "no existe" y dejamos que put()
            // falle luego si hay un problema real de red.
            $exists = false;
        }

        if ($exists) {
            $stats['skipped']++;
            Log::info(message: 'backfill.key_already_exists', context: [
                'image_id' => $image->id,
                'storage_key' => $newKey,
            ]);

            return;
        }

        // Upload + update atomicos. Si el upload retorna false o el
        // update falla, la transaccion hace rollback — el image_url
        // queda intacto en la DB.
        try {
            DB::transaction(callback: function () use ($image, $newKey, $content): void {
                $uploaded = $this->filesystem->disk(name: 'ftp')->put(
                    path: $newKey,
                    contents: $content,
                );

                if ($uploaded !== true) {
                    throw new RuntimeException(message: 'FTP upload returned false');
                }

                $image->update(attributes: ['image_url' => $newKey]);
            });
            $stats['uploaded']++;
        } catch (Throwable $e) {
            $stats['failed']++;
            Log::warning(message: 'backfill.upload_or_update_failed', context: [
                'image_id' => $image->id,
                'storage_key' => $newKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolver el contenido binario desde la fuente actual.
     *
     * Estrategia:
     *   - http(s)://  →  Http::timeout()->get($url)
     *   - /            →  Storage::disk('local')->get(ltrim($path, '/'))
     *   - otros        →  null (formato no soportado en esta fase)
     *
     * Lanza Throwable en caso de fetch failure o HTTP no-2xx (para que
     * el caller lo loguee y cuente como failed).
     */
    private function resolveContent(string $imageUrl): ?string
    {
        if (str_starts_with(haystack: $imageUrl, needle: 'http://')
            || str_starts_with(haystack: $imageUrl, needle: 'https://')
        ) {
            $response = Http::timeout(seconds: self::HTTP_TIMEOUT_SECONDS)->get(url: $imageUrl);

            if (! $response->successful()) {
                throw new RuntimeException(
                    message: sprintf('HTTP %d fetching %s', $response->status(), $imageUrl),
                );
            }

            return $response->body();
        }

        if (str_starts_with(haystack: $imageUrl, needle: '/')) {
            $relativePath = ltrim(string: $imageUrl, characters: '/');
            $contents = $this->filesystem->disk(name: 'local')->get(path: $relativePath);

            if ($contents === null || $contents === false) {
                return null;
            }

            return $contents;
        }

        return null;
    }

    /**
     * Extraer la extension normalizada del path original.
     *
     * Devuelve null si el path no tiene extension o si la extension no
     * esta en `StorageKeyDeriver::EXTENSIONS`. Esto evita que el
     * `derive()` lance InvalidArgumentException y se cuente como
     * failure sin contexto.
     */
    private function extractExtension(string $imageUrl): ?string
    {
        if ($imageUrl === '') {
            return null;
        }

        $path = parse_url(url: $imageUrl, component: PHP_URL_PATH);

        if (! is_string(value: $path) || $path === '') {
            return null;
        }

        // pathinfo() con PATHINFO_EXTENSION retorna string en runtime,
        // pero PHPStan no puede narrow el tipo a partir del int flag y
        // devuelve `array|string`. Narrow explícito para satisfacer
        // phpstan y blindar el type contract.
        $rawExtension = pathinfo(path: $path, flags: PATHINFO_EXTENSION);
        $extension = is_string(value: $rawExtension) ? $rawExtension : '';

        if ($extension === '') {
            return null;
        }

        $extension = strtolower(string: $extension);

        return in_array(needle: $extension, haystack: StorageKeyDeriver::EXTENSIONS, strict: true)
            ? $extension
            : null;
    }

    /**
     * Loguear skip estructurado (no es un error pero queremos trace).
     */
    private function logSkip(ProductImage $image, string $reason): void
    {
        Log::info(message: 'backfill.skip', context: [
            'image_id' => $image->id,
            'reason' => $reason,
            'image_url' => $image->image_url,
        ]);
    }
}
