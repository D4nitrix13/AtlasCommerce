<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\GalleryRoleAuditor;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;

/**
 * ⚠️ Lee antes de usar
 * ===================
 *
 * `assets:audit-gallery` compara el árbol de `assets/products/{tenant}/`
 * contra `product_images` y reporta dónde divergen. El criterio es el
 * hash: el `storage_key` es `sha256(binario)[0..12]` y el path de storage
 * no lleva variante, así que un mismo binario produce siempre la misma
 * key y las divergencias se detectan sin heurísticas.
 *
 * Cuatro hallazgos, en orden de gravedad:
 *
 *   1. ROLE CONFLICT — el mismo binario con dos roles distintos en dos
 *      variantes (`front` en una, `headstock` en otra). Señal de un role
 *      mal nombrado, y de una fila que se perdió: la BD guarda una sola
 *      de las dos, así que la otra variante queda sin esa foto (y a
 *      veces sin primary).
 *   2. SHARED PHOTO — el mismo binario con el MISMO rol en dos variantes
 *      (el left-handed reusando las fotos del diestro). No es un error de
 *      naming; es una foto compartida que igual pierde su fila.
 *   3. UNPARSEABLE — archivos cuyo nombre no matchea `{role}-{WxH}.webp`
 *      (típico: el slug del producto repetido en el nombre). El seeder
 *      los descarta en silencio, así que esos productos quedan SIN
 *      galería. Ninguna reparación de BD los salva: hay que renombrar el
 *      archivo, y eso es intervención manual.
 *   4. DIRECTORIO SIN PRODUCTO — la carpeta del producto existe en el
 *      árbol pero no hay fila en `products`. No hay a qué colgar nada,
 *      así que ni `--fix` ni un backfill lo resuelven: hay que decidir si
 *      el producto se importa. Antes esto se descartaba en silencio.
 *
 * LO QUE NO DETECTA: un `front` cuyo contenido es un primer plano del
 * clavijero. El binario es único y la fila dice exactamente lo que el
 * archivo dice: no hay forma de verlo sin mirar las fotos.
 *
 * USO
 * ---
 *   php artisan assets:audit-gallery                                   # diagnóstico (default)
 *   php artisan assets:audit-gallery --tenant=instrumentos-musicales-la-voz
 *   php artisan assets:audit-gallery --fix                             # inserta las filas faltantes
 *   php artisan assets:audit-gallery --fix --storage-disk=local        # no toca FTP
 *
 * `--fix` sólo INSERTA filas que el árbol reclama y la BD no tiene.
 * No borra, no modifica filas existentes y no toca ningún archivo del
 * árbol de assets. Es idempotente.
 */
final class AuditGalleryAssetsCommand extends Command
{
    protected $signature = 'assets:audit-gallery
        {--tenant=instrumentos-musicales-la-voz : Tenant slug}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or default)}
        {--storage-disk=local : Storage disk name (local / ftp), informativo}
        {--dry-run : Sólo diagnóstico. Es el default; se acepta explícito}
        {--fix : Inserta en product_images las filas que el árbol reclama y faltan}';

    protected $description = 'Audita si product_images es un espejo fiel de assets/products/{tenant}/.';

    public function handle(StorageKeyDeriver $deriver): int
    {
        $assetBase = $this->option('asset-base')
            ?: env(key: 'ATLAS_ASSET_BASE', default: base_path('../../assets'));
        $tenant = (string) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');
        $fix = (bool) $this->option('fix');

        if ($fix && $dryRun) {
            $this->error('❌ --fix y --dry-run son excluyentes: el default ya es diagnóstico.');

            return self::FAILURE;
        }

        $tenantPath = rtrim(string: $assetBase, characters: '/').'/products/'.$tenant;
        if (! is_dir(filename: $tenantPath)) {
            $this->error("❌ No existe: {$tenantPath}");

            return self::FAILURE;
        }

        $this->info('=== AuditGalleryAssets ===');
        $this->line("Asset base:   {$tenantPath}/");
        $this->line('Storage disk: '.$this->option('storage-disk'));
        $this->line('Mode:         '.($fix ? 'FIX' : 'DRY-RUN'));
        $this->line('');

        $auditor = new GalleryRoleAuditor(
            deriver: $deriver,
            filenames: new AssetFilenameParser,
            assetBase: (string) $assetBase,
            tenantSlug: $tenant,
        );

        $report = $auditor->audit();

        $this->info('Árbol de assets');
        $this->line("  productos:            {$report['products_on_disk']}");
        $this->line("  archivos:             {$report['files_on_disk']}");
        $this->line("  archivos parseables:  {$report['parseable_files']}");

        $this->renderCollisions(title: 'CONFLICTOS DE ROL (mismo binario, roles distintos)', collisions: $report['role_conflicts']);
        $this->renderCollisions(title: 'FOTOS COMPARTIDAS (mismo binario, mismo rol, otra variante)', collisions: $report['shared_photos']);

        $this->info('ARCHIVOS QUE EL SEEDER NO PUEDE LEER (requiere renombrar el archivo)');
        if ($report['unparseable'] === []) {
            $this->line('  ✅ ninguno');
        } else {
            $byProduct = [];
            foreach ($report['unparseable'] as $entry) {
                $byProduct[$entry['product']][] = basename($entry['path']);
            }
            foreach ($byProduct as $product => $files) {
                $this->line("  ⚠️  {$product}: ".count($files).' archivos');
                $this->line('      '.implode(separator: ', ', array: array_slice(array: $files, offset: 0, length: 2)));
                if (count($files) > 2) {
                    $this->line('      … +'.(count($files) - 2).' más');
                }
            }
        }

        $this->info('VARIANTES SIN IMAGEN PRIMARY');
        if ($report['variants_without_primary'] === []) {
            $this->line('  ✅ ninguna');
        } else {
            foreach ($report['variants_without_primary'] as $entry) {
                $this->line("  ⚠️  {$entry['product']} / {$entry['variant']}");
            }
        }

        $this->info('DIRECTORIOS SIN PRODUCTO EN LA BD (no se puede reparar desde acá)');
        if ($report['products_without_db_row'] === []) {
            $this->line('  ✅ ninguno');
        } else {
            $total = array_sum(array_column($report['products_without_db_row'], 'claims'));
            foreach ($report['products_without_db_row'] as $entry) {
                $this->line("  ⚠️  {$entry['product']}: {$entry['claims']} archivos huérfanos");
            }
            $this->line('  total: '.count($report['products_without_db_row'])." directorios, {$total} archivos.");
            $this->line('     Están en el árbol pero no en `products`: hay que decidir si se importan.');
        }

        $this->info('FILAS QUE FALTAN EN product_images');
        if ($report['missing_rows'] === []) {
            $this->line('  ✅ la BD es un espejo fiel del árbol');
        } else {
            $unresolved = 0;
            $byProduct = [];
            foreach ($report['missing_rows'] as $missing) {
                if ($missing['variant_id'] === null) {
                    $unresolved++;
                }
                $byProduct[$missing['product']][] = $missing;
            }
            foreach ($byProduct as $product => $rows) {
                $this->line("  ⚠️  {$product}: ".count($rows).' filas');
            }
            $this->line('  total: '.count($report['missing_rows'])." (variante sin resolver: {$unresolved})");
        }

        if (! $fix) {
            $this->line('');
            $this->line('Dry-run: no se escribió nada. Repetí con --fix para insertar las filas faltantes.');

            return self::SUCCESS;
        }

        $result = $auditor->repair(report: $report);
        $this->line('');
        $this->info('Filas insertadas: '.$result['inserted']);
        $this->line('Filas omitidas (ya estaban o sin variante resoluble): '.$result['skipped']);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $collisions
     */
    private function renderCollisions(string $title, array $collisions): void
    {
        $this->info($title);
        if ($collisions === []) {
            $this->line('  ✅ ninguno');

            return;
        }

        $byProduct = [];
        foreach ($collisions as $collision) {
            $byProduct[$collision['product']][] = $collision;
        }
        foreach ($byProduct as $product => $items) {
            $this->line("  ⚠️  {$product}: ".count($items).' colisiones');
            foreach (array_slice(array: $items, offset: 0, length: 5) as $collision) {
                $claims = array_map(
                    static fn (array $c): string => "{$c['variant']}→{$c['role']}",
                    array: $collision['claims'],
                );
                $this->line('      '.$collision['storage_key'].' ('.$collision['width'].'px): '.implode(separator: ', ', array: $claims));
            }
            if (count($items) > 5) {
                $this->line('      … +'.(count($items) - 5).' más');
            }
        }
    }
}
