<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Models\Catalog\Product;
use App\Models\Stores\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte una imagen subida por el admin a WebP y la publica en el
 * disk de assets, deriving la clave con el MISMO
 * {@see StorageKeyDeriver} que usan el seeder y el auditor.
 *
 * ── Por qué un WebP por fila y no uno solo ────────────────────────────
 *
 * El schema de `product_images` es UNA fila por (foto, ancho): la columna
 * `width` existe justamente para eso. Lo dice el propio seeder —
 * "sin el ancho persistido, las 4 resoluciones de una misma foto son filas
 * indistinguibles y la galería muestra la misma foto 4 veces". Por eso
 * `upload()` devuelve un registro por ancho y el controller crea una fila
 * por cada uno: los WebP comparten `image_url`-shape, `alt_text` y
 * `sort_order`, y se distinguen sólo por `width`.
 *
 * ── De dónde salen los anchos ─────────────────────────────────────────
 *
 * De `assets.upload.widths` (`config/assets.php`), resueltos y validados
 * por {@see UploadWidthResolver}. Esta clase ya no tiene constante de
 * anchos: con la lista en config, una constante sería una segunda fuente
 * de verdad capaz de contradecir al archivo de configuración sin que nada
 * lo reportara.
 *
 * ── Por qué no se upscalea ────────────────────────────────────────────
 *
 * Un 800 px generado desde un original de 500 px no agrega detalle: agrega
 * bytes y borrosa. Por eso se descartan los anchos que superan el ancho
 * del original. Un JPEG de 1000 px produce dos registros (400 y 800), no
 * cuatro, y eso es preferible a cuatro filas donde dos apuntan al mismo
 * binario y por lo tanto a la misma `storage_key`.
 *
 * ── Requisito de runtime ──────────────────────────────────────────────
 *
 * La conversión usa `spatie/image` in-process, que resuelve al driver GD
 * cuando Imagick no está instalado. Eso exige `ext-gd` CON soporte WebP
 * en el binario PHP que corre el request: sin la extensión no hay
 * conversor posible y `load()` falla con
 * {@see CouldNotLoadImage}. No es una degradación elegante, es una
 * precondición de despliegue.
 */
final class ProductImageUploader
{
    public function __construct(
        private readonly StorageKeyDeriver $deriver,
        private readonly UploadWidthResolver $widths,
    ) {}

    /**
     * Convierte, publica y devuelve una entrada por ancho generado.
     *
     * @param  UploadedFile  $file  Binario ya validado por el FormRequest.
     * @param  Product  $product  Dueño de las filas; su `slug` es el `entity` de la clave.
     * @param  Store  $store  Tenant bajo el que se publica el asset.
     * @return array<int, array{width: int, image_url: string}>
     *
     * @throws CouldNotLoadImage Si el archivo no se puede decodificar.
     * @throws InvalidArgumentException Si `assets.upload.widths` está mal
     *                                  configurada. Es un error de despliegue,
     *                                  no del archivo: se propaga para que el
     *                                  request quede en 5xx con el detalle en
     *                                  el log en lugar de devolver un 201 con
     *                                  una galería que nadie pidió.
     */
    public function upload(UploadedFile $file, Product $product, Store $store): array
    {
        $disk = Storage::disk(name: (string) config(key: 'assets.disk', default: 'ftp'));
        $quality = (int) config(key: 'assets.upload.quality', default: 85);

        // Antes de tocar el archivo: si la lista de anchos está rota, el
        // resultado sería el mismo de todas formas.
        $widths = $this->widths->resolve();

        $sourcePath = $file->getRealPath();
        if ($sourcePath === false) {
            abort(code: Response::HTTP_UNPROCESSABLE_ENTITY, message: 'No pudimos leer el archivo subido.');
        }

        // El ancho del original se mide una vez sobre una decodificación
        // descartable: el bucle de abajo vuelve a cargar el archivo por
        // cada ancho porque GD no tiene clonado barato (ver toWebp()).
        $sourceWidth = Image::load(pathToImage: $sourcePath)->getWidth();

        // Se descartan los anchos que no entran: ver la nota de no-upscale
        // en el docblock de la clase.
        $targetWidths = array_values(array_filter(
            $widths,
            static fn (int $width): bool => $width <= $sourceWidth,
        ));

        if ($targetWidths === []) {
            abort(
                code: Response::HTTP_UNPROCESSABLE_ENTITY,
                message: 'La imagen es más angosta que el tamaño mínimo soportado ('.min($widths).' px).',
            );
        }

        $stored = [];

        foreach ($targetWidths as $width) {
            $binary = $this->toWebp(sourcePath: $sourcePath, width: $width, quality: $quality);

            $storageKey = $this->deriver->derive(
                tenantSlug: $store->slug,
                collection: 'products',
                entity: $product->slug,
                content: $binary,
                extension: 'webp',
            );

            $disk->put(path: $storageKey, contents: $binary);

            $stored[] = ['width' => $width, 'image_url' => $storageKey];
        }

        return $stored;
    }

    /**
     * Re-escala a un ancho exacto conservando el aspect ratio del original
     * y devuelve el WebP como binario.
     *
     * Carga el archivo en cada llamada a propósito: el `resize()` de GD
     * reescribe el recurso de imagen in-place, así que reutilizar una
     * instancia compartida (o un `clone` raso, que comparte el driver)
     * encadenaría los anchos — el 800 se calcularía sobre el 400 ya
     * reducido y saldría un upscale envuelto en dos pasos. Decodificar de
     * nuevo cuesta una lectura de disco por ancho; el costo es aceptable
     * porque el upload es una operación de administración, no del camino
     * caliente del storefront.
     *
     * `save()` —y no `base64()`— porque `base64()` escribe el WebP con la
     * calidad por defecto de libwebp e ignora el `quality()` configurado;
     * el repo ya publica a calidad 85 y el output tiene que coincidir.
     */
    private function toWebp(string $sourcePath, int $width, int $quality): string
    {
        $temporaryPath = tempnam(directory: sys_get_temp_dir(), prefix: 'atlas-webp-');

        try {
            Image::load(pathToImage: $sourcePath)
                ->width(width: $width, constraints: [Constraint::PreserveAspectRatio])
                ->quality(quality: $quality)
                ->format(format: 'webp')
                ->save(path: (string) $temporaryPath);

            $binary = file_get_contents(filename: (string) $temporaryPath);

            if ($binary === false || $binary === '') {
                throw CouldNotLoadImage::make((string) $temporaryPath);
            }

            return $binary;
        } finally {
            // El temporal no es un asset: si no se borra, cada request
            // deja basura en el tmp del container.
            if (is_file(filename: (string) $temporaryPath)) {
                @unlink(filename: (string) $temporaryPath);
            }
        }
    }
}
