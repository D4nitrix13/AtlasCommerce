<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Models\Catalog\Product;
use App\Services\Catalog\ProductPhotoGrouper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource storefront público del producto.
 *
 * Mapea el modelo `products` (snake_case + varias tablas relacionadas)
 * a la shape `Product` del frontend (`frontend/src/types/catalog.ts`,
 * camelCase) que la PDP y el listado ya esperan.
 *
 * Decisiones de shape (documentadas — cualquier cambio rompe contrato):
 *
 *  - `priceCents` viene de `products.base_price_cents` SIN transformación.
 *    Si la BD tiene el precio en 0 (caso del seeder La Voz cargado
 *    actualmente), la API lo expone tal cual; nunca se inventa un valor.
 *
 *  - `imageUrl`: la fila "principal" de `product_images`. Resolución:
 *      1. `is_primary = true` y `sort_order` mínimo si hay varias marcadas.
 *      2. Primera fila ordenada por `sort_order`.
 *      3. Fallback EXPLICITO a `/images/product-placeholder.png` (mismo
 *         placeholder hardcoded que `lib/product-detail.ts` usa). NO se
 *         construye una URL de CDN/runtime porque eso acoplaría la API
 *         a un hostname configurable y rompería el contrato actual.
 *
 *  - `images`: array con shape dependiente del contexto (ver
 *    `resolveImages()` para el detalle):
 *      - PDP (variants eager-loaded): objetos
 *        `{url, altText, isPrimary, isHover, variantId, sortOrder, srcset}`.
 *        Una entrada POR FOTO de galería (`sort_order` es 1:1 con
 *        `CatalogAssetsSeeder::ROLE_ORDER`): la de mayor resolución, con
 *        `srcset` apuntando a las demás resoluciones de la misma foto.
 *        `variantId` permite a la PDP filtrar la galería por acabado, e
 *        `isHover` le dice a la card cuál de esas entradas cambia al
 *        pasar el cursor. `isHover` viaja JUNTO al `srcset` de la misma
 *        entrada, que es lo que la card necesita para pintar el hover en
 *        cualquier ancho: no hay que reconstruir el srcset en el cliente.
 *      - Listado (variants NO eager-loaded): lista plana de URLs, sin
 *        agrupar — el contrato del listado no cambia.
 *    Se OMITE del payload cuando `images` no fue eager-loaded.
 *
 *  - `category`: `categories.slug`. Si el producto no tiene categoría
 *    o la relación no fue cargada, string vacío '' para no romper el
 *    contrato `category: string`.
 *
 *  - `brand`: `brands.name`. Se OMITE del payload cuando el producto
 *    no tiene brand asociado (relación null) o la relación no fue
 *    cargada — el consumer lo trata como opcional.
 *
 *  - `stock`: suma de stock de las `product_variants` activas cuando
 *    la relación fue cargada; 0 si no se cargó. La UI usa `stock > 0`
 *    para el flag "in stock".
 *
 *  - `isExclusive`: emitido SOLO cuando es `true`. Evita inflar el
 *    payload del listado con un flag redundante para los 161/162
 *    productos que no lo son.
 *
 *  - `hasCondition` / `hasSizes`: flags del producto. Se exponen
 *    SIEMPRE (la PDP los lee para decidir qué selectores renderizar;
 *    enviar `false` es comportamiento esperado, no ausencia).
 *
 *  - `currency`: emitido solo cuando la relación `currency` fue cargada
 *    Y existe; opcional para el frontend que ya tiene default 'USD'.
 *
 *  - `variants`: array de variantes del producto. Emitido SOLO cuando
 *    la relación `variants` fue eager-loaded por el controller (lo hace
 *    el endpoint `show()` del PDP; el `index()` del listado no). Shape
 *    por variante: `{id, name, finishSlug, finishLabel, finishHex,
 *    condition, stock, handedness}`. Variantes con `is_active=false` o
 *    sin `finish_slug` se filtran porque la PDP no las necesita.
 *    Permite a la PDP derivar el picker de acabado y de mano desde el
 *    backend en lugar de caer al set hardcoded de Saber Alter.
 *
 * Prevención de N+1: las relaciones usadas en este Resource (images,
 * category, brand, variants, currency) deben eager-loadearse via
 * `with([...])` en el controller. Sin eager-load, los campos
 * dependientes quedan ausentes (lo cual es seguro — el frontend los
 * trata como opcionales). El campo `variants` requiere eager-load
 * explícito: si el controller no lo cargó, el Resource cae al shape
 * legacy sin variantes (listado público), preservando el contrato.
 *
 * Wrap del JSON: este Resource usa `static $wrap = null` para emitir
 * la forma plana (no `{data: [...]}`) en ambos endpoints, ya que el
 * listado storefront (`StorefrontProductsList.tsx`) consume un array
 * plano de productos y cambiar a `{data: ...}` lo rompería sin razón.
 *
 * @property Product $resource
 */
class StorefrontProductResource extends JsonResource
{
    /**
     * Placeholder local cuando el producto no tiene imagen asociada.
     *
     * Path relativo al bundle público del frontend. Es el MISMO que
     * `frontend/src/lib/product-detail.ts:PLACEHOLDER_IMAGE`. Decisión
     * consciente: un path local servible siempre, sin depender de
     * CDN/almacenamiento configurable.
     */
    private const PLACEHOLDER_IMAGE_PATH = '/images/product-placeholder.svg';

    /**
     * Override del wrapper por defecto (`'data'`). El storefront espera
     * arrays planos en `index()` y shapes planas en `show()`.
     */
    public static $wrap = null;

    /**
     * Definición compartida de qué es una foto dentro de `product_images`.
     *
     * Se resuelve de forma perezosa en vez de inyectarse por constructor:
     * `Resource::collection()` instancia vía `mapInto`, que pasa la clave
     * de la colección como segundo argumento y rompía el constructor
     * tipado.
     */
    private ?ProductPhotoGrouper $photos = null;

    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    private function photoGrouper(): ProductPhotoGrouper
    {
        return $this->photos ??= new ProductPhotoGrouper;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageData = $this->resolveImages();
        $primaryUrl = $imageData['primary'];
        $hasVariantContext = $this->resource->relationLoaded('variants');

        $payload = [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            // Contract: `description: string` requerido.
            // products.description es nullable en BD → '' para no romper TS.
            'description' => $this->resource->description ?? '',
            'priceCents' => (int) $this->resource->base_price_cents,
            'imageUrl' => $primaryUrl ?? self::PLACEHOLDER_IMAGE_PATH,
            'category' => $this->resource->category?->slug ?? '',
            'stock' => $this->resolveStock(),
            'hasCondition' => (bool) $this->resource->has_condition,
            'hasSizes' => (bool) $this->resource->has_sizes,
        ];

        // Shape de `images` depende del contexto:
        //   - PDP (variants cargado): objetos con `variantId` para
        //     filtrar la galería por acabado. Si no hay objetos (sin
        //     images eager-loaded), no emitimos el campo.
        //   - Listado (variants NO cargado): lista de URLs, más liviana
        //     para response de 346 productos.
        if ($hasVariantContext) {
            if ($imageData['objects'] !== []) {
                $payload['images'] = $imageData['objects'];
            }
        } elseif ($imageData['all'] !== []) {
            $payload['images'] = $imageData['all'];
        }

        if (
            $this->resource->relationLoaded('brand')
            && $this->resource->brand !== null
        ) {
            $payload['brand'] = $this->resource->brand->name;
        }

        if ($this->resource->is_exclusive === true) {
            $payload['isExclusive'] = true;
        }

        if (
            $this->resource->relationLoaded('currency')
            && $this->resource->currency !== null
        ) {
            $payload['currency'] = $this->resource->currency->code;
        }

        // Variantes del producto (con finish, condition, stock, etc.).
        // Se emiten SOLO cuando el eager-load se disparó (la PDP las
        // necesita; el listado no las usa). El shape exacto está
        // documentado en cada campo de resolveVariants().
        if ($hasVariantContext) {
            $payload['variants'] = $this->resolveVariants();
        }

        // Tallas o capacidades del producto. Cada una puede llevar
        // valor numérico + unidad: la taza de 11 oz es `value: 11,
        // unit: 'oz'`; la talla M de ropa no mide nada y deja ambos
        // en null. NULL cuando el producto no vende por tallas.
        if ($this->resource->relationLoaded('sizes')) {
            $payload['sizes'] = $this->resource->sizes
                ->sortBy('position')
                ->values()
                ->map(fn ($size): array => [
                    'slug' => $size->slug,
                    'label' => $size->label,
                    'value' => $size->value,
                    'unit' => $size->unit,
                ])
                ->all();
        }

        // Atributos EAV del producto (material, cuidados, peso…).
        if ($this->resource->relationLoaded('attributeValues')) {
            $payload['attributes'] = $this->resource->attributeValues
                ->map(fn ($value): array => [
                    'slug' => $value->attribute?->slug,
                    'label' => $value->attribute?->name,
                    'type' => $value->attribute?->type,
                    'unit' => $value->attribute?->unit,
                    'value' => $value->value,
                    'valueNumber' => $value->value_number,
                    'valueBoolean' => $value->value_boolean,
                ])
                ->filter(fn (array $row): bool => $row['slug'] !== null)
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * Resuelve el set de imágenes y la URL principal del producto.
     *
     * Si la relación `images` no fue eager-loadada por el controller,
     * devuelve `all: []` y `primary: null`. El consumer trata el array
     * vacío como ausencia y el Resource cae al placeholder en el
     * toArray principal.
     *
     * Shape del array devuelto:
     *   - `all`  — lista de URLs (sin metadata) para endpoints que solo
     *              necesitan URLs (listado, sitemap, etc.).
     *   - `objects` — lista de objetos `{url, altText, isPrimary,
     *                  isHover, variantId, sortOrder, srcset}` para el
     *                  PDP. Una entrada POR FOTO (la de mayor resolución)
     *                  con el `srcset` de las demás resoluciones de esa
     *                  foto. `isHover` y el `srcset` viajan juntos, así que
     *                  el cliente no tiene que reconstruir nada para pintar
     *                  el swap al pasar el cursor.
     *                  Solo se popula cuando la relación `variants`
     *                  también fue eager-loadada (señal de que el
     *                  consumer es el detalle). En el listado emitimos
     *                  objetos vacíos para mantener el shape consistente
     *                  y permitir fallback del frontend.
     *
     * @return array{all: list<string>, objects: list<array<string, mixed>>, primary: string|null}
     */
    private function resolveImages(): array
    {
        if (! $this->resource->relationLoaded('images')) {
            return ['all' => [], 'objects' => [], 'primary' => null];
        }

        $images = $this->resource->images->sortBy('sort_order')->values();

        // El listado NO se agrupa: su contrato es una lista plana de URLs
        // y las tarjetas sólo toman la primera / la primary. Agrupar acá
        // multiplicaría el trabajo del Resource sin cambiar nada visible.
        $allUrls = $images->pluck('image_url')
            ->map(fn (mixed $url): string => $this->toProxyUrl((string) $url))
            ->all();

        $objects = $this->galleryPerRole($images->all());

        $primary = $images->firstWhere('is_primary', true);
        $primaryUrl = $primary !== null ? $this->toProxyUrl((string) $primary->image_url) : null;

        return ['all' => $allUrls, 'objects' => $objects, 'primary' => $primaryUrl];
    }

    /**
     * Colapsa las resoluciones de una misma foto en UNA entrada de galería.
     *
     * El árbol de assets guarda cada foto en 4 resoluciones
     * (`{role}-{400,800,1200,1600}x{...}.webp`) y el seeder persiste las
     * cuatro como filas de `product_images`. Como el `storage_key` es el
     * SHA-256 del binario, el rol no viaja en la URL pero SÍ viaja en
     * `sort_order`: es 1:1 con `CatalogAssetsSeeder::ROLE_ORDER`
     * (front=1, back=2, body=3, …). El ancho vive en `product_images.width`.
     *
     * La unidad de agrupación es (variant, rol): una foto de cada
     * acabado y cada rol. La clave vive en
     * {@see ProductPhotoGrouper::keyOf()}, que es la misma que usan el
     * backfill de `is_hover` y el reordenamiento de la galería.
     *
     * Regla de degradación (deliberada):
     *
     *   - Grupo con AL MENOS una fila de ancho conocido → una sola entrada:
     *     la de mayor `width`, con `srcset` armando las resoluciones
     *     restantes. Las filas NO se borran: quedan servibles por el
     *     `srcset`, que es exactamente para lo que existen.
     *   - Grupo donde NINGUNA fila tiene `width` (filas heredadas de
     *     seeds previos, sin pasar por `assets:backfill-image-variant`) →
     *     NO se colapsa. Elegir una fila al azar haría del slide
     *     principal un thumbnail de 400px, que es peor que mostrar las
     *     cuatro. `srcset` queda vacío y el browser usa `src` solo.
     *
     * @param  array<int, mixed>  $images  Imágenes ordenadas por `sort_order`.
     * @return list<array<string, mixed>>
     */
    private function galleryPerRole(array $images): array
    {
        // El agrupamiento es el MISMO de {@see ProductPhotoGrouper}: una
        // foto es la terna (variant_id, sort_order). El grouper se comparte
        // con el backfill de `is_hover` (que marca por foto) y con
        // `ProductImageController::reorder()` (que reescribe por foto).
        // Divergir entre los tres es la receta para que el storefront y el
        // panel admin hablen de fotos distintas sin que nada lo delate.
        //
        // La clave incluye la variante y NO sólo el `sort_order`: el
        // `sort_order` es el RANK del rol dentro del catálogo (front=1,
        // back=2, …) y es el mismo número para los seis acabados de un
        // producto multi-acabado. Agrupar sólo por `sort_order` colapsa las
        // fotos de acabados distintos en una entrada y deja la galería del
        // PDP con las fotos de un solo acabado.
        $objects = [];

        foreach ($this->photoGrouper()->group(images: $images) as $photo) {
            $rows = $photo['rows'];

            if (! $this->photoGrouper()->hasKnownWidths(rows: $rows)) {
                foreach ($rows as $image) {
                    $objects[] = $this->galleryEntry(image: $image, srcset: '');
                }

                continue;
            }

            $representative = $this->photoGrouper()->representative(rows: $rows);
            // No-null por construcción: `hasKnownWidths` garantiza al
            // menos una fila con `width`, y `representative` nunca devuelve
            // null para un grupo no vacío. El assert documenta el invariante
            // para quien lea, y falla ruidoso si el grouper cambia.
            assert($representative !== null);

            $objects[] = $this->galleryEntry(
                image: $representative,
                srcset: $this->buildSrcset(roleImages: $rows),
            );
        }

        return $objects;
    }

    /**
     * Normaliza una `product_images` a la shape que consume la PDP.
     *
     * @param  mixed  $image  Modelo `ProductImage`.
     * @param  string  $srcset  `srcset` ya construido, o `''` si el rol no tiene resoluciones conocidas.
     * @return array<string, mixed>
     */
    private function galleryEntry(mixed $image, string $srcset): array
    {
        return [
            'url' => $this->toProxyUrl((string) $image->image_url),
            'altText' => (string) ($image->alt_text ?? ''),
            'isPrimary' => (bool) $image->is_primary,
            // `isHover` viaja en la entrada representante de la foto, que
            // es la que la card matchea con el `<img data-role="back">`.
            // El `srcset` de ESTA MISMA entrada es el de la foto de hover,
            // así que el cliente puede pintar el swap en cualquier ancho sin
            // reconstruir nada. La bandera está replicada en las N
            // resoluciones: el storefront contrae la foto a una entrada y
            // por eso el cliente nunca ve N flags en `true`.
            'isHover' => (bool) $image->is_hover,
            // variantId nullable: si la imagen no tiene variant asociado
            // (caso 4/19 multi-variant con finish_slug real pero assets
            // no presentes en disco), el frontend no puede filtrar y
            // degrada a mostrar todas.
            'variantId' => $image->variant_id,
            'sortOrder' => (int) $image->sort_order,
            'srcset' => $srcset,
        ];
    }

    /**
     * Arma el `srcset` de un rol con todas sus resoluciones conocidas.
     *
     * Los descriptores son `{ancho}w` — sintaxis estándar de srcset, la
     * que el browser usa para elegir el candidato. Como cada resolución
     * tiene su propio `storage_key` (hash del binario), el candidato se
     * construye con la URL del proxy de ESA fila, no con un token de
     * tamaño en el path.
     *
     * @param  array<int, mixed>  $roleImages  Todas las filas de la foto.
     */
    private function buildSrcset(array $roleImages): string
    {
        $candidates = [];

        foreach ($roleImages as $image) {
            if ($image->width === null || $image->width <= 0) {
                continue;
            }
            $candidates[(int) $image->width] = $this->toProxyUrl((string) $image->image_url);
        }

        ksort($candidates);

        $parts = [];
        foreach ($candidates as $width => $url) {
            $parts[] = "{$url} {$width}w";
        }

        return implode(', ', $parts);
    }

    /**
     * Resuelve la lista de variantes para el PDP.
     *
     * Shape por variante (camelCase, fiel a frontend ProductVariant):
     *   - id           int   (PK de product_variants; id estable)
     *   - name         string
     *   - finishSlug   string|null
     *   - finishLabel  string|null
     *   - finishHex    string|null
     *   - condition    string
     *   - stock        int
     *   - handedness   string|null  ('left-handed' o null = diestro)
     *
     * `handedness` se emite VERBATIM, sin normalizar: `NULL` en BD
     * significa "regular / diestro" según la convención de la migración
     * `2026_10_19_160000_add_left_handed_flags_to_products`, y el
     * consumer decide cómo presentarlo. El campo es aditivo — el
     * frontend lo trata como opcional para no romper clientes old.
     *
     * Variantes con `is_active = false` se filtran (no aparecen en el
     * PDP). Variantes sin `finish_slug` también se filtran — son
     * ambiguas para el picker de acabado, y la PDP no las necesita.
     *
     * @return list<array<string, mixed>>
     */
    private function resolveVariants(): array
    {
        $rows = [];
        foreach ($this->resource->variants as $variant) {
            if (! ($variant->is_active ?? true)) {
                continue;
            }
            if ($variant->finish_slug === null || $variant->finish_slug === '') {
                continue;
            }
            $rows[] = [
                'id' => (int) $variant->id,
                'name' => (string) ($variant->name ?? ''),
                'finishSlug' => (string) $variant->finish_slug,
                'finishLabel' => (string) ($variant->finish_label ?? $variant->finish_slug),
                'finishHex' => $variant->finish_hex,
                'condition' => (string) ($variant->condition ?? 'nuevo'),
                'stock' => (int) ($variant->stock ?? 0),
                'handedness' => $variant->handedness,
                // Talla o capacidad de la variante — NULL cuando el
                // producto no vende por tallas (guitarras, figuras).
                'sizeSlug' => $variant->size?->slug,
                'sizeLabel' => $variant->size?->label,
                'sizeValue' => $variant->size?->value,
                'sizeUnit' => $variant->size?->unit,
            ];
        }

        return $rows;
    }

    /**
     * Convierte el storage key de una imagen en la URL del proxy que la
     * sirve: `/api/v1/storefront/assets/{storage_key}`.
     *
     * El AssetController resuelve el archivo desde el disco configurado y
     * valida que el path viva bajo el namespace del tenant, así que el
     * cliente no necesita conocer el backend de storage. Es una URL
     * relativa a propósito: no ata la API a ningún hostname.
     *
     * Los valores que ya son URL absoluta se respetan sin tocar.
     */
    private function toProxyUrl(string $storageKey): string
    {
        if (
            str_starts_with($storageKey, 'http://')
            || str_starts_with($storageKey, 'https://')
        ) {
            return $storageKey;
        }

        return '/api/v1/storefront/assets/'.ltrim($storageKey, '/');
    }

    /**
     * Suma de stock de las variants activas del producto. Si la relación
     * `variants` no fue eager-loadada, devuelve 0 — el contrato del
     * frontend considera "stock desconocido" equivalente a 0 para la
     * UI (no muestra "in stock").
     */
    private function resolveStock(): int
    {
        if (! $this->resource->relationLoaded('variants')) {
            return 0;
        }

        return (int) $this->resource->variants->sum('stock');
    }
}
