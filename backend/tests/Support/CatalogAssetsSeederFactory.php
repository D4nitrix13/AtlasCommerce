<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\CatalogAssetsSeeder;
use App\Services\Assets\StorageKeyDeriver;
use App\Services\Catalog\VariantHandednessResolver;

/**
 * Helper compartido para los tests de `assets:seed-catalog`.
 *
 * Encapsula:
 *   - Setup de disk local-fake (idempotente entre tests)
 *   - Creación de webps con bytes únicos (el `storage_key` es el hash
 *     del binario, así que dos archivos idénticos colapsan en una sola
 *     fila de `product_images`)
 *   - Invocación del seeder y de `verify()` directo
 *
 * Lo extraje para que los tres archivos de test del seeder
 * (`SeedCatalogAssetsCommandTest`, `SeedCatalogAssetsPersistenceTest`,
 * `SeedCatalogAssetsVerifyTest`) tengan el mismo plumbing sin repetir.
 */
final class CatalogAssetsSeederFactory
{
    public function __construct(
        private readonly string $tempAssetBase,
        private readonly string $tenantSlug,
    ) {}

    /**
     * Crea un webp con contenido único en disco dentro del asset tree
     * del tenant. Devuelve el path absoluto para encadenar asserts.
     */
    public function createWebp(string $relativePath): string
    {
        $full = $this->tempAssetBase.'/products/'.$this->tenantSlug.'/'.$relativePath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Bytes únicos por archivo: el storage_key es el hash del binario.
        file_put_contents($full, 'RIFF'.random_bytes(4).'WEBPVP8 '.random_bytes(24));

        return $full;
    }

    /**
     * Corre el seeder directo (sin pasar por artisan, porque el
     * commando agrega validación de path-existence en seco que los
     * tests no quieren).
     *
     * Devuelve 0 cuando no hubo errores, 1 cuando los hubo — mirror
     * del contrato del commando `assets:seed-catalog`.
     */
    public function runSeeder(): int
    {
        $seeder = $this->seeder();

        $stats = $seeder->seed();

        return $stats['errors'] === [] ? 0 : 1;
    }

    /**
     * Constructor común del seeder para los métodos de este helper.
     */
    private function seeder(): CatalogAssetsSeeder
    {
        return new CatalogAssetsSeeder(
            assetBase: $this->tempAssetBase,
            tenantSlug: $this->tenantSlug,
            storageDisk: 'local-fake',
            deriver: new StorageKeyDeriver,
            filenames: new AssetFilenameParser,
            handedness: new VariantHandednessResolver,
            backupBase: null,
        );
    }

    /**
     * Instancia el seeder con su wiring completo y devuelve el resultado
     * de `verify()` en la shape pública del método (lo que el commando
     * imprime al final del `handle`).
     *
     * @return array{
     *   ok: bool,
     *   issues: array<int, string>,
     *   stats: array<string, int>,
     * }
     */
    public function verify(): array
    {
        return $this->seeder()->verify();
    }
}
