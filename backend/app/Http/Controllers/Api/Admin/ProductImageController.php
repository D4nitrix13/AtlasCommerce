<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageReorderRequest;
use App\Http\Requests\Admin\ProductImageStoreRequest;
use App\Http\Requests\Admin\ProductImageUpdateRequest;
use App\Http\Requests\Admin\ProductImageUploadRequest;
use App\Http\Resources\Catalog\AdminProductImageResource;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Services\Assets\ProductImageUploader;
use App\Services\Assets\UploadWidthResolver;
use App\Services\Catalog\ProductPhotoGrouper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de imágenes de producto (RF-CAT-022).
 *
 * Las imágenes viven anidadas bajo un producto:
 *   /api/admin/products/{product}/images
 *
 * Aislamiento por tenant: si el producto no está publicado en
 * ninguna tienda del admin actual, devuelve 404.
 *
 * Lógica especial:
 *  - Al marcar una imagen como principal (is_primary=true), se
 *    desmarcan las demás del mismo producto (solo una principal
 *    por producto).
 *  - `is_hover` sigue la misma regla y el mismo alcance: UNA foto de
 *    hover por producto. Es la ranura que la card muestra al pasar el
 *    cursor, y la elige el administrador — no el `alt_text`.
 *  - Una foto no puede ser portada y hover a la vez: el conflicto se
 *    rechaza (422) en vez de resolverlo en silencio, porque hacerlo
 *    significa cambiar la portada o el hover de una foto que el admin no
 *    tocó.
 *  - sort_order auto-asignado al final (max + 1) si no se pasa.
 *  - variant_id, si viene, tiene que ser una variante del producto de
 *    la ruta. La regla vive en los FormRequests (ver
 *    ValidatesVariantOwnership), no acá: los tres endpoints que escriben
 *    la columna la declaran y el controller sólo la copia.
 *
 * Permiso: `catalog.manage`.
 */
class ProductImageController extends Controller
{
    use CursorPaginates;

    public function __construct(private readonly ProductImageUploader $uploader) {}

    /**
     * GET /admin/products/{product}/images
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - is_primary: bool.
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id);

        if ($request->has('is_primary')) {
            $query->where('is_primary', $request->boolean('is_primary'));
        }

        $paginator = $query
            ->ordered()
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminProductImageResource::class,
        );
    }

    /**
     * GET /admin/products/{product}/images/{image}
     */
    public function show(Product $product, ProductImage $image): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ]);
    }

    /**
     * POST /admin/products/{product}/images
     */
    public function store(ProductImageStoreRequest $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $data = $request->validated();
        $isPrimary = (bool) ($data['is_primary'] ?? false);
        $isHover = (bool) ($data['is_hover'] ?? false);

        $this->assertNotBothSlots(isPrimary: $isPrimary, isHover: $isHover);

        $image = DB::transaction(function () use ($data, $product, $isPrimary, $isHover): ProductImage {
            if ($isPrimary) {
                $this->clearPrimaryFlag(productId: $product->id);
            }

            if ($isHover) {
                $this->clearHoverFlag(productId: $product->id);
            }

            $sortOrder = $data['sort_order'] ?? $this->nextSortOrder(productId: $product->id);

            return ProductImage::query()->create(attributes: [
                'product_id' => $product->id,
                'image_url' => $data['image_url'],
                'alt_text' => $data['alt_text'] ?? null,
                'is_primary' => $isPrimary,
                'is_hover' => $isHover,
                'sort_order' => $sortOrder,
                // Ya validada contra el producto de la ruta: una variante
                // ajena nunca llega acá (ver ValidatesVariantOwnership).
                'variant_id' => $data['variant_id'] ?? null,
            ]);
        });

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * POST /admin/products/{product}/images/upload
     *
     * Ruta GEMELA de `store()` pero por binario: el admin sube el archivo,
     * el backend lo convierte a WebP en los anchos de
     * `assets.upload.widths` ({@see UploadWidthResolver}) y crea UNA fila
     * por ancho.
     *
     * No reemplaza al endpoint con `image_url`: aquel sigue siendo el
     * contrato para cuando la imagen ya vive fuera (CDN de terceros,
     * material provisto por la marca). Agregar el archivo como campo
     * opcional del `store()` obligaría a definir cuál de los dos gana
     * cuando vienen juntos, y partirlo en dos rutas hace que cada
     * endpoint tenga una forma de entrada y una de salida.
     *
     * Todas las filas de una misma subida comparten `sort_order`, `alt_text`,
     * `variant_id`, `is_primary` e `is_hover`, y se distinguen sólo por
     * `width` — que es como el seeder del catálogo ya guarda las 4
     * resoluciones de una foto.
     * `is_primary` sólo se marca en la fila del ancho mayor: la convención
     * del repo es que la primaria es la de 1600.
     * `is_hover` se replica en LAS CUATRO. La card cambia a la foto de
     * hover con `srcset`, y si sólo la fila más ancha quedara marcada,
     * cualquier lectura que no colapse por rol (o que colapse mal)
     * devolvería una sola de las cuatro resoluciones.
     */
    public function upload(ProductImageUploadRequest $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $data = $request->validated();
        $isPrimary = (bool) ($data['is_primary'] ?? false);
        $isHover = (bool) ($data['is_hover'] ?? false);
        $store = $this->resolveTenantStore(product: $product);

        $this->assertNotBothSlots(isPrimary: $isPrimary, isHover: $isHover);

        $stored = $this->uploader->upload(
            file: $request->uploadedImage(),
            product: $product,
            store: $store,
        );

        $images = DB::transaction(function () use ($stored, $product, $data, $isPrimary, $isHover): Collection {
            if ($isPrimary) {
                $this->clearPrimaryFlag(productId: $product->id);
            }

            if ($isHover) {
                $this->clearHoverFlag(productId: $product->id);
            }

            $sortOrder = $data['sort_order'] ?? $this->nextSortOrder(productId: $product->id);
            $altText = $data['alt_text'] ?? null;
            // Las resoluciones de UNA foto son la misma foto: la variante
            // se replica en las N filas, no se elige para una sola.
            $variantId = $data['variant_id'] ?? null;

            // Los anchos llegan ascendentes, así que el último es el mayor.
            $widestWidth = (int) $stored[array_key_last($stored)]['width'];

            $created = [];

            foreach ($stored as $entry) {
                $created[] = ProductImage::query()->create(attributes: [
                    'product_id' => $product->id,
                    'image_url' => $entry['image_url'],
                    'alt_text' => $altText,
                    'is_primary' => $isPrimary && $entry['width'] === $widestWidth,
                    'is_hover' => $isHover,
                    'sort_order' => $sortOrder,
                    'width' => $entry['width'],
                    'variant_id' => $variantId,
                ]);
            }

            return collect($created);
        });

        return response()->json(data: [
            'data' => AdminProductImageResource::collection(resource: $images)->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/products/{product}/images/{image}
     */
    public function update(
        ProductImageUpdateRequest $request,
        Product $product,
        ProductImage $image,
    ): JsonResponse {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);

        $data = $request->validated();
        $becomingPrimary = array_key_exists('is_primary', $data) && (bool) $data['is_primary'];
        $becomingHover = array_key_exists('is_hover', $data) && (bool) $data['is_hover'];

        // El conflicto se mira contra el estado que la petición produce, no
        // contra el estado actual: promover a hover una foto que en ESTA
        // misma llamada se vuelve primaria es el conflicto, y también al revés.
        $resultingIsPrimary = $becomingPrimary || ($image->is_primary && ! $this->setsSlotToFalse(
            data: $data,
            slot: 'is_primary',
        ));
        $resultingIsHover = $becomingHover || ($image->is_hover && ! $this->setsSlotToFalse(
            data: $data,
            slot: 'is_hover',
        ));

        $this->assertNotBothSlots(isPrimary: $resultingIsPrimary, isHover: $resultingIsHover);

        $image = DB::transaction(function () use ($data, $image, $product, $becomingPrimary, $becomingHover): ProductImage {
            if ($becomingPrimary) {
                $this->clearPrimaryFlag(productId: $product->id);
            }

            if ($becomingHover) {
                $this->clearHoverFlag(productId: $product->id);
            }

            $image->update(attributes: $data);

            return $image;
        });

        return response()->json(data: [
            'data' => (new AdminProductImageResource(resource: $image))->resolve(),
        ]);
    }

    /**
     * PUT /admin/products/{product}/images/order
     *
     * Reordena la galería completa del producto.
     *
     * Contrato (documentado en `ProductImageReorderRequest`): `image_ids`
     * es el juego COMPLETO de ids de imagen del producto en el orden
     * nuevo. Cualquier desviación —falta uno, sobra uno, se repite, alguno
     * es de otro producto— es 422 y no escribe nada. Se valida el juego
     * entero ANTES de abrir la transacción: si se validara dentro, un
     * error a mitad de camino dejaría la galería a medio ordenar.
     *
     * El reordenamiento es a nivel de FOTO, no de fila: todas las
     * resoluciones de una foto comparten `sort_order` (por eso el
     * `srcset` de cada entrada de galería sigue armándose). Se agrupan
     * las filas por su terna `(variant_id, sort_order)` actual ANTES de
     * escribir, así que el agrupamiento no se autodestruye mientras se
     * reescribe.
     *
     * Los ids no tienen que venir contiguos por foto: manda la primera
     * aparición de cada una.
     */
    public function reorder(ProductImageReorderRequest $request, Product $product): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);

        $incomingIds = $request->imageIds();
        $productImageIds = ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->pluck(column: 'id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $this->assertOrderIsComplete(
            incomingIds: $incomingIds,
            productImageIds: $productImageIds,
        );

        // El reordenamiento real va dentro de la transacción: agrupar por
        // foto y reescribir `sort_order` son el mismo paso atómico, y
        // agrupar por el `sort_order` viejo sólo es válido hasta que la
        // primera fila cambió de posición.
        $reorder = DB::transaction(function () use ($incomingIds, $product): array {
            $images = ProductImage::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->orderBy(column: 'id')
                ->get();

            $rowsByGroup = [];
            foreach ($images as $image) {
                $rowsByGroup[ProductPhotoGrouper::keyOf(image: $image)][] = $image;
            }

            $groupByImageId = [];
            foreach ($rowsByGroup as $group => $rows) {
                foreach ($rows as $row) {
                    $groupByImageId[(int) $row->id] = $group;
                }
            }

            $nextSortOrder = 0;
            $seenGroups = [];

            foreach ($incomingIds as $imageId) {
                $group = $groupByImageId[$imageId] ?? null;

                if ($group === null || isset($seenGroups[$group])) {
                    continue;
                }

                $seenGroups[$group] = true;

                ProductImage::query()
                    ->whereIn(column: 'id', values: array_map(
                        static fn (ProductImage $row): int => (int) $row->id,
                        $rowsByGroup[$group],
                    ))
                    ->update(['sort_order' => $nextSortOrder]);

                $nextSortOrder++;
            }

            return ProductImage::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->orderBy(column: 'sort_order')
                ->orderBy(column: 'id')
                ->get()
                ->all();
        });

        return response()->json(data: [
            'data' => AdminProductImageResource::collection(resource: $reorder)->resolve(),
        ]);
    }

    /**
     * DELETE /admin/products/{product}/images/{image}
     *
     * Borrado DURO: `ProductImage` no usa `SoftDeletes`, así que la fila
     * y sus flags desaparecen juntos y no hay nada que limpiar. Es la
     * respuesta al invariante de las dos ranuras: si la foto de hover se
     * borra, el producto queda sin hover y la card degrada a no-intercambiar
     * (que es exactamente lo que pasaba antes, cuando la cascada de roles
     * dejaba de encontrar su foto).
     *
     * Lo que NO hace es re-elegir portada si la foto borrada era la
     * primaria: elegir por el admin es una decisión, y la API no adivina.
     */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->assertProductInTenantScope(product: $product);
        $this->assertImageBelongsToProduct(image: $image, product: $product);
        $image->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: tienda bajo la que se publica el asset de una imagen.
     *
     * El `storage_key` es `tenants/{slug}/products/{product-slug}/{hash}.webp`
     * y el `AssetController` sólo sirve lo que cuelga del tenant que
     * resolvió el dominio de la request. Guardar bajo el slug equivocado
     * produce una imagen que el admin ve creada y el storefront sirve como
     * 403 — un fallo invisible hasta que se navega el PDP.
     *
     * Se resuelve desde el producto y NO desde el Host porque estas rutas
     * no llevan `store.domain`: el alcance real ya lo fija
     * `assertProductInTenantScope()` (el producto tiene que estar publicado
     * en una tienda del admin). Si el producto está en varias de sus
     * tiendas se toma la de menor id para que el resultado sea
     * determinista; el repo asume una instancia = un tenant de catálogo,
     * así que el caso no debería ocurrir, pero "el primero" sin orden sería
     * una fila distinta por request.
     */
    private function resolveTenantStore(Product $product): Store
    {
        $user = auth()->user();

        // NO se usa `$product->stores()` para esto. Esa relación declara
        // `belongsToMany(related: ProductStore::class, table: 'product_stores')`
        // — devuelve filas del pivot, no tiendas — así que filtrar por
        // `stores.id` sobre ella arma un self-join que PostgreSQL rechaza
        // con `Duplicate alias: table name "product_stores" specified more
        // than once`. Es el mismo camino que ya usa
        // `ProductController::assertProductInTenantScope()`: pivot por
        // `store_id`, y recién ahí la tienda.
        $pivot = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->whereIn(column: 'store_id', values: $user->stores()->pluck('stores.id')->all())
            ->orderBy(column: 'store_id')
            ->first();

        $store = $pivot instanceof ProductStore
            ? Store::query()->find($pivot->store_id)
            : null;

        if (! $store instanceof Store) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }

        return $store;
    }

    /**
     * Helper: desmarca todas las imágenes primarias de un producto.
     * Llamar dentro de una transacción que ya marca la nueva primaria.
     */
    private function clearPrimaryFlag(int $productId): void
    {
        ProductImage::query()
            ->where('product_id', $productId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Helper: desmarca todas las filas de hover de un producto.
     *
     * Espejo exacto de `clearPrimaryFlag()`, y por el mismo motivo: la
     * bandera es del producto, no de la foto, y sólo una puede estar
     * marcada. Se desmarcan TODAS las filas, no una foto: la bandera está
     * replicada en las 4 resoluciones, y desmarcar sólo la de 1600 dejaría
     * el resto mintiendo.
     *
     * Llamar dentro de una transacción que ya marca la nueva hover.
     */
    private function clearHoverFlag(int $productId): void
    {
        ProductImage::query()
            ->where('product_id', $productId)
            ->where('is_hover', true)
            ->update(['is_hover' => false]);
    }

    /**
     * Helper: rechaza que una misma foto ocupe las dos ranuras.
     *
     * Portada y hover son excluyentes por diseño, no por gusto: son dos
     * posiciones de la MISMA pantalla y una foto no puede estar en las dos.
     * Se rechaza con 422 en vez de resolverlo, porque cualquier
     * resolución automática cambia una ranura que el administrador no
     * pidió cambiar — y una card cuya portada desaparece al marcar un
     * hover es un bug silencioso, no una ayuda.
     */
    private function assertNotBothSlots(bool $isPrimary, bool $isHover): void
    {
        if (! ($isPrimary && $isHover)) {
            return;
        }

        abort(
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            message: 'Una imagen no puede ser principal y de hover a la vez.',
        );
    }

    /**
     * Helper: ¿la petición está bajando explícitamente esta ranura a
     * `false`?
     *
     * `array_key_exists` solo no alcanza: `['is_hover' => false]` está
     * presente pero NO deja de ser hover — al revés, la baja. Por eso se
     * mira el valor.
     *
     * @param  array<string, mixed>  $data
     */
    private function setsSlotToFalse(array $data, string $slot): bool
    {
        return array_key_exists($slot, $data) && ! (bool) $data[$slot];
    }

    /**
     * Helper: el juego de ids del payload es exactamente el del producto.
     *
     * Se compara el JUEGO, no el orden ni el tamaño: lo que se garantiza es
     * que no falta ninguna imagen y que ninguna es ajena. El orden es
     * libre y lo resuelve el agrupamiento por foto.
     *
     * @param  list<int>  $incomingIds
     * @param  list<int>  $productImageIds
     */
    private function assertOrderIsComplete(array $incomingIds, array $productImageIds): void
    {
        $missing = array_values(array_diff($productImageIds, $incomingIds));
        $foreign = array_values(array_diff($incomingIds, $productImageIds));

        if ($missing === [] && $foreign === []) {
            return;
        }

        abort(
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            message: 'El orden tiene que incluir todas las imágenes del producto, y sólo ellas. '
                .'Faltan: '.(string) implode(', ', $missing).'. Sobran: '.(string) implode(', ', $foreign).'.',
        );
    }

    /**
     * Helper: siguiente sort_order disponible (= max + 1, default 0).
     */
    private function nextSortOrder(int $productId): int
    {
        $max = (int) ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->max(column: 'sort_order');

        return $max + 1;
    }

    /**
     * Helper: valida que el producto esté en el scope del admin. 404 si no.
     */
    private function assertProductInTenantScope(Product $product): void
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\Auth\User) {
            abort(code: 401);
        }

        $accessible = $user->stores()->pluck('stores.id')->all();

        $inScope = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->whereIn(column: 'store_id', values: $accessible)
            ->exists();

        if (! $inScope) {
            abort(code: 404, message: 'Producto no encontrado en tu tenant.');
        }
    }

    /**
     * Helper: valida que la imagen pertenece al producto de la URL.
     * Si no, 404 (evita fuga de datos cross-product).
     */
    private function assertImageBelongsToProduct(ProductImage $image, Product $product): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            abort(code: 404, message: 'Imagen no encontrada para este producto.');
        }
    }
}
