<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Models\Catalog\Product;
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
 *  - `images`: array de URLs ordenado por `sort_order` ASC. Se OMITE
 *    del payload cuando `images` no fue eager-load (controller decide).
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
 * Prevención de N+1: las relaciones usadas en este Resource (images,
 * category, brand, variants, currency) deben eager-loadearse via
 * `with([...])` en el controller. Sin eager-load, los campos
 * dependientes quedan ausentes (lo cual es seguro — el frontend los
 * trata como opcionales).
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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageData = $this->resolveImages();
        $images = $imageData['all'];
        $primaryUrl = $imageData['primary'];

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

        if ($images !== []) {
            $payload['images'] = $images;
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
     * @return array{all: list<string>, primary: string|null}
     */
    private function resolveImages(): array
    {
        if (! $this->resource->relationLoaded('images')) {
            return ['all' => [], 'primary' => null];
        }

        $images = $this->resource->images->sortBy('sort_order')->values();

        $allUrls = $images->pluck('image_url')
            ->map(fn (mixed $url): string => $this->toProxyUrl((string) $url))
            ->all();

        $primary = $images->firstWhere('is_primary', true);
        $primaryUrl = $primary !== null ? $this->toProxyUrl((string) $primary->image_url) : null;

        return ['all' => $allUrls, 'primary' => $primaryUrl];
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

        return '/api/v1/storefront/assets/' . ltrim($storageKey, '/');
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
